<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Tests\Unit;

use BeckonBilling\ApiClient\Collection;
use BeckonBilling\ApiClient\Model\Customer;
use BeckonBilling\ApiClient\Model\OutboundInvoice;
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
}
