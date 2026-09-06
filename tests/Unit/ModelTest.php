<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Tests\Unit;

use BeckonBilling\ApiClient\Collection;
use BeckonBilling\ApiClient\Model\Customer;
use BeckonBilling\ApiClient\Model\OutboundInvoice;
use BeckonBilling\ApiClient\Model\RecurringInvoice;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    public function testPropertyAndArrayAccess(): void
    {
        $customer = new Customer(['id' => 'c1', 'label' => 'ACME', 'reverse_charge' => true]);

        $this->assertSame('ACME', $customer->label);
        $this->assertSame('ACME', $customer['label']);
        $this->assertSame('c1', $customer->id());
        $this->assertTrue(isset($customer->label));
        $this->assertNull($customer->does_not_exist);
        $this->assertSame('fallback', $customer->get('missing', 'fallback'));
        $this->assertTrue($customer->has('reverse_charge'));
    }

    public function testUnknownFieldsRemainReachable(): void
    {
        // Forward-compatibility: a field the client predates is still readable.
        $customer = new Customer(['id' => 'c1', 'brand_new_field' => 'value']);
        $this->assertSame('value', $customer->brand_new_field);
    }

    public function testToArrayAndJsonSerialize(): void
    {
        $data = ['id' => 'c1', 'label' => 'ACME'];
        $customer = new Customer($data);

        $this->assertSame($data, $customer->toArray());
        $this->assertSame(json_encode($data), json_encode($customer));
    }

    public function testImmutability(): void
    {
        $customer = new Customer(['id' => 'c1']);

        $this->expectException(\LogicException::class);
        $customer['label'] = 'nope';
    }

    public function testTypedHelpers(): void
    {
        $paid = new OutboundInvoice(['id' => 'i1', 'status' => 'issued', 'paid' => true]);
        $draft = new OutboundInvoice(['id' => 'i2', 'status' => 'draft', 'paid' => false]);

        $this->assertTrue($paid->isPaid());
        $this->assertFalse($paid->isDraft());
        $this->assertTrue($draft->isDraft());
        $this->assertFalse($draft->isPaid());
    }

    /**
     * The whole point of isOutstanding(): a cancelled invoice reports paid=false
     * and no money ever arrived, yet nothing is owed - the credit note that
     * reversed it carries the balance. Reading `paid` alone calls this a debt.
     */
    public function testCancelledInvoiceIsNotOutstanding(): void
    {
        $cancelled = new OutboundInvoice([
            'id' => 'i3',
            'status' => 'issued',
            'paid' => false,
            'cancelled' => true,
            'remaining_amount' => 0.0,
            'settlement_status' => 'cancelled',
            'cancelled_by_outbound_invoice_number' => '2026-1166',
        ]);

        $this->assertTrue($cancelled->isCancelled());
        $this->assertFalse($cancelled->isPaid());
        $this->assertFalse($cancelled->isOutstanding());
        $this->assertSame('cancelled', $cancelled->settlementStatus());
        $this->assertSame('2026-1166', $cancelled->cancelled_by_outbound_invoice_number);
    }

    public function testOutstandingAndSettledInvoices(): void
    {
        $open = new OutboundInvoice([
            'id' => 'i4', 'status' => 'issued', 'paid' => false,
            'cancelled' => false, 'remaining_amount' => 3775.0, 'settlement_status' => 'open',
        ]);
        $part = new OutboundInvoice([
            'id' => 'i5', 'status' => 'issued', 'paid' => false,
            'cancelled' => false, 'remaining_amount' => 190.0, 'settlement_status' => 'partially_paid',
        ]);
        $settled = new OutboundInvoice([
            'id' => 'i6', 'status' => 'issued', 'paid' => true,
            'cancelled' => false, 'remaining_amount' => 0.0, 'settlement_status' => 'paid',
        ]);
        $draft = new OutboundInvoice([
            'id' => 'i7', 'status' => 'draft', 'paid' => false,
            'cancelled' => false, 'remaining_amount' => 0.0, 'settlement_status' => 'draft',
        ]);

        $this->assertTrue($open->isOutstanding());
        $this->assertTrue($part->isOutstanding(), 'a partially paid invoice still owes the rest');
        $this->assertFalse($settled->isOutstanding());
        $this->assertFalse($draft->isOutstanding(), 'a draft owes nothing until it is issued');
        $this->assertFalse($open->isCancelled());
    }

    public function testCollectionPagination(): void
    {
        $collection = new Collection(
            [new Customer(['id' => 'c1']), new Customer(['id' => 'c2'])],
            5,
            2,
            0,
        );

        $this->assertCount(2, $collection);
        $this->assertTrue($collection->hasMore());
        $this->assertSame(2, $collection->nextOffset());

        $last = new Collection([new Customer(['id' => 'c5'])], 5, 2, 4);
        $this->assertFalse($last->hasMore());
        $this->assertNull($last->nextOffset());
    }

    /**
     * The two helpers on Order. `isSettled()` reads a stamp the API sets and
     * clears again when the invoice is cancelled - a missing key must answer
     * false rather than throw, so an older payload stays usable.
     */
    public function testOrderHelpers(): void
    {
        $offen = new \BeckonBilling\ApiClient\Model\Order(['status' => 'in_progress']);
        $this->assertTrue($offen->isOpen());
        $this->assertFalse($offen->isSettled());

        $archiviert = new \BeckonBilling\ApiClient\Model\Order(['status' => 'archived']);
        $this->assertFalse($archiviert->isOpen());

        $abgerechnet = new \BeckonBilling\ApiClient\Model\Order([
            'status' => 'completed',
            'settled_at' => '2026-09-01T08:00:00+02:00',
        ]);
        $this->assertTrue($abgerechnet->isSettled());

        // Weder Status noch Stempel: keine Ausnahme, sondern zweimal nein.
        $leer = new \BeckonBilling\ApiClient\Model\Order([]);
        $this->assertFalse($leer->isOpen());
        $this->assertFalse($leer->isSettled());
    }

    /**
     * A recurring invoice carries its attachments twice. `document_uuids` is
     * the replacement; `document_ids` is deprecated but still on the wire, so
     * BOTH have to survive a round trip - a client that dropped either one on
     * deserialisation would break a caller mid-transition.
     */
    public function testRecurringInvoiceCarriesBothAttachmentLists(): void
    {
        $payload = [
            'id' => 'r1',
            'label' => 'Hosting',
            'document_ids' => [17, 42],
            'document_uuids' => ['1f2e3d4c-0000-4000-8000-000000000001', '9a8b7c6d-0000-4000-8000-000000000002'],
        ];
        $template = new RecurringInvoice($payload);

        $this->assertSame([17, 42], $template->document_ids);
        $this->assertSame($payload['document_uuids'], $template->document_uuids);

        // The typed accessors read the same payload, and toArray/json keep the
        // whole thing - serialising a model back must not lose either key.
        $this->assertSame([17, 42], $template->documentIds());
        $this->assertSame($payload['document_uuids'], $template->documentUuids());
        $this->assertSame($payload, $template->toArray());
        $this->assertSame(json_encode($payload), json_encode($template));
    }

    /**
     * A payload without the keys answers with empty lists, never an error: a
     * template with no attachments is ordinary, and so is a response from an
     * API older than `document_uuids`.
     */
    public function testRecurringInvoiceAttachmentAccessorsTolerateAMissingKey(): void
    {
        $template = new RecurringInvoice(['id' => 'r1']);

        $this->assertSame([], $template->documentIds());
        $this->assertSame([], $template->documentUuids());
    }
}
