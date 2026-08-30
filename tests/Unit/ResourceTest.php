<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Tests\Unit;

use BeckonBilling\ApiClient\Collection;
use BeckonBilling\ApiClient\Exception\ConflictException;
use BeckonBilling\ApiClient\Exception\GoneException;
use BeckonBilling\ApiClient\Exception\ValidationException;
use BeckonBilling\ApiClient\Model\ArticleVariant;
use BeckonBilling\ApiClient\Model\Customer;
use BeckonBilling\ApiClient\Model\DocumentTemplate;
use BeckonBilling\ApiClient\Model\OutboundInvoice;
use BeckonBilling\ApiClient\Model\Quote;
use BeckonBilling\ApiClient\Model\Unit;
use BeckonBilling\ApiClient\Tests\Support\ClientTestCase;
use BeckonBilling\ApiClient\Tests\Support\MockHttpClient;

final class ResourceTest extends ClientTestCase
{
    public function testCreateReturnsHydratedModel(): void
    {
        $http = (new MockHttpClient())->push(200, ['id' => 'c1', 'label' => 'ACME']);
        $customer = $this->makeClient($http)->customers->create(['label' => 'ACME']);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertSame('c1', $customer->id());
        $this->assertSame('ACME', $customer->label);
    }

    public function testListReturnsCollectionWithPaginationMeta(): void
    {
        $http = (new MockHttpClient())->push(200, [
            'data' => [['id' => 'c1'], ['id' => 'c2']],
            'total' => 42,
            'limit' => 2,
            'offset' => 0,
        ]);

        $page = $this->makeClient($http)->customers->list(['limit' => 2]);

        $this->assertInstanceOf(Collection::class, $page);
        $this->assertCount(2, $page);
        $this->assertSame(42, $page->total);
        $this->assertSame(2, $page->limit);
        $this->assertTrue($page->hasMore());
        $this->assertContainsOnlyInstancesOf(Customer::class, iterator_to_array($page));
    }

    public function testAutoPagingWalksEveryPage(): void
    {
        $http = (new MockHttpClient())
            ->push(200, ['data' => [['id' => 'c1'], ['id' => 'c2']], 'total' => 3, 'limit' => 2, 'offset' => 0])
            ->push(200, ['data' => [['id' => 'c3']], 'total' => 3, 'limit' => 2, 'offset' => 2]);

        $ids = [];
        foreach ($this->makeClient($http)->customers->autoPaging(['limit' => 2]) as $customer) {
            $ids[] = $customer->id();
        }

        $this->assertSame(['c1', 'c2', 'c3'], $ids);
        $this->assertSame('2', $this->queryOf($http->requests[1])['offset']);
    }

    public function testUpdateSendsPut(): void
    {
        $http = (new MockHttpClient())->push(200, ['id' => 'c1', 'contact_email' => 'x@y.z']);
        $this->makeClient($http)->customers->update('c1', ['contact_email' => 'x@y.z']);

        $request = $http->lastRequest();
        $this->assertSame('PUT', $request->getMethod());
        $this->assertStringContainsString('/customers/c1', (string) $request->getUri());
        $this->assertSame(['contact_email' => 'x@y.z'], $this->bodyOf($request));
    }

    public function testDeleteSendsDelete(): void
    {
        $http = (new MockHttpClient())->push(200, []);
        $this->makeClient($http)->customers->delete('c1');

        $this->assertSame('DELETE', $http->lastRequest()->getMethod());
    }

    public function testQuoteIssueSendPdf(): void
    {
        $http = (new MockHttpClient())
            ->push(200, ['id' => 'q1', 'status' => 'issued'])
            // The send response is an ENVELOPE, which is what the API really sends.
            ->push(200, ['sent_to' => 'kunde@example.com', 'quote' => ['id' => 'q1', 'status' => 'issued']])
            ->push(200, '%PDF-quote', ['Content-Type' => 'application/pdf']);

        $client = $this->makeClient($http);

        $issued = $client->quotes->issue('q1');
        $this->assertInstanceOf(Quote::class, $issued);
        $this->assertStringEndsWith('/quotes/q1/issue', explode('?', (string) $http->requests[0]->getUri())[0]);

        $client->quotes->send('q1', ['document_ids' => ['d1']]);
        $this->assertStringEndsWith('/send', explode('?', (string) $http->requests[1]->getUri())[0]);
        $this->assertSame(['document_ids' => ['d1']], $this->bodyOf($http->requests[1]));

        $pdf = $client->quotes->pdf('q1');
        $this->assertSame('%PDF-quote', $pdf);
    }

    /**
     * `POST /quotes/{id}/convert` was retired on 2026-08-28 and now answers
     * 410 `quote_conversion_moved` on every call - a won quote becomes an
     * order, and the order is what gets invoiced, through a route this API
     * does not expose yet. The client method is kept (deprecated) rather than
     * removed, but it must fail LOCALLY with a typed, catchable exception -
     * never spend a request on a call that can only ever be refused, and
     * never let a raw error escape uncategorised.
     */
    public function testConvertIsRetiredAndThrowsLocallyWithoutARequest(): void
    {
        $http = new MockHttpClient();
        $client = $this->makeClient($http);

        try {
            $client->quotes->convert('q1');
            $this->fail('convert() must throw now that the route is retired');
        } catch (GoneException $e) {
            $this->assertSame(410, $e->getStatusCode());
            $this->assertSame('quote_conversion_moved', $e->getErrorKey());
        }

        // A scope argument changes nothing - every call is refused the same way.
        try {
            $client->quotes->convert('q1', 'deposit');
            $this->fail('convert() must throw regardless of scope');
        } catch (GoneException $e) {
            $this->assertSame('quote_conversion_moved', $e->getErrorKey());
        }

        $this->assertSame([], $http->requests, 'no request may leave the client for a retired route');
    }

    /**
     * Quote send is the one action whose body is an envelope, not a bare quote.
     * Hydrating the envelope as the Quote made every field null and buried the
     * real one at ->quote.
     */
    public function testQuoteSendUnwrapsTheEnvelope(): void
    {
        $http = (new MockHttpClient())->push(200, [
            'sent_to' => 'kunde@example.com',
            'quote' => ['id' => 'q1', 'public_index' => '2608-1000', 'status' => 'issued'],
            'detached_positions' => [['position' => 2, 'title' => 'Altes Produkt']],
        ]);

        $client = $this->makeClient($http);
        $quote = $client->quotes->send('q1');

        $this->assertSame('q1', $quote->id());
        $this->assertSame('2608-1000', $quote->public_index);

        $http2 = (new MockHttpClient())->push(200, [
            'sent_to' => 'kunde@example.com',
            'quote' => ['id' => 'q1'],
            'detached_positions' => [['position' => 2, 'title' => 'Altes Produkt']],
        ]);
        $full = $this->makeClient($http2)->quotes->sendResult('q1');

        $this->assertSame('kunde@example.com', $full['sent_to']);
        $this->assertInstanceOf(Quote::class, $full['quote']);
        $this->assertCount(1, $full['detached_positions']);
    }

    /**
     * Issuing reports two best-effort failures on a 200 body. A client that
     * branches on the status code alone never learns about either.
     */
    public function testIssueSurfacesBothSoftFailures(): void
    {
        $http = (new MockHttpClient())->push(200, [
            'id' => 'inv1',
            'status' => 'issued',
            'send_error' => 'SMTP is not configured.',
            'payment_link_error' => 'Stripe key rejected.',
        ]);

        $issued = $this->makeClient($http)->outboundInvoices->issue('inv1');

        $this->assertSame('SMTP is not configured.', $issued->send_error);
        $this->assertSame('Stripe key rejected.', $issued->payment_link_error);
    }

    public function testUnitsAndDocumentTemplatesAreReadable(): void
    {
        $http = (new MockHttpClient())
            ->push(200, [
                'data' => [
                    [
                        'id' => 'piece', 'key' => 'piece',
                        'de' => ['short' => 'Stück', 'label' => 'Stück', 'plural' => ''],
                        'en' => ['short' => 'piece', 'label' => 'Piece', 'plural' => 'pieces'],
                    ],
                    [
                        'id' => 'month', 'key' => 'month',
                        'de' => ['short' => 'Monat', 'label' => 'Monat', 'plural' => 'Monate'],
                        'en' => ['short' => 'month', 'label' => 'Month', 'plural' => 'months'],
                    ],
                ],
                'total' => 2, 'limit' => 100, 'offset' => 0,
            ])
            ->push(200, [
                'id' => 'month', 'key' => 'month',
                'de' => ['short' => 'Monat', 'label' => 'Monat', 'plural' => 'Monate'],
                'en' => ['short' => 'month', 'label' => 'Month', 'plural' => 'months'],
            ])
            ->push(200, [
                'data' => [['id' => 't1', 'kind' => 'invoice', 'days' => 0, 'is_default' => true]],
                'total' => 1, 'limit' => 100, 'offset' => 0,
            ]);

        $client = $this->makeClient($http);

        $units = $client->units->list();
        $this->assertContainsOnlyInstancesOf(Unit::class, iterator_to_array($units));
        $this->assertStringEndsWith('/units', explode('?', (string) $http->requests[0]->getUri())[0]);
        // The printed forms live UNDER a language - `$unit->short` and
        // `$unit->plural` were removed in 0.12.0, and a flat fixture here would
        // have gone on passing against a shape the server no longer sends.
        $this->assertSame('piece', iterator_to_array($units)[0]->key);
        $this->assertSame('Stück', iterator_to_array($units)[0]->de['short']);
        // An empty plural means "does not inflect", not "missing".
        $this->assertSame('', iterator_to_array($units)[0]->de['plural']);
        $this->assertSame('Monate', iterator_to_array($units)[1]->de['plural']);
        // The key is what an article's `unit` and a position's `unit_key` must
        // be - never a printed form, which is why the two differ here.
        $this->assertNotSame(
            iterator_to_array($units)[0]->key,
            iterator_to_array($units)[0]->de['short']
        );

        $this->assertInstanceOf(Unit::class, $client->units->get('month'));

        // The kind is `invoice`, not the `outbound_invoice` /document-terms used.
        $templates = $client->documentTemplates->list(['kind' => 'invoice']);
        $this->assertContainsOnlyInstancesOf(DocumentTemplate::class, iterator_to_array($templates));
        $this->assertStringEndsWith(
            '/document-templates',
            explode('?', (string) $http->requests[2]->getUri())[0]
        );
        $this->assertSame('invoice', $this->queryOf($http->requests[2])['kind']);
        // 0 days is a real term ("due immediately"), never "unset".
        $this->assertSame(0, iterator_to_array($templates)[0]->days);
    }

    /**
     * Read-only means it fails HERE, without spending a request on a 405.
     */
    public function testReadOnlyResourcesRefuseWrites(): void
    {
        $http = new MockHttpClient();
        $client = $this->makeClient($http);

        foreach ([
            fn () => $client->units->create(['key' => 'kg']),
            fn () => $client->units->update('kg', ['key' => 'kg']),
            fn () => $client->units->delete('kg'),
            fn () => $client->documentTemplates->create(['label' => 'x']),
            fn () => $client->documentTemplates->update('t1', ['label' => 'x']),
            fn () => $client->documentTemplates->delete('t1'),
        ] as $write) {
            try {
                $write();
                $this->fail('a write on a read-only resource must throw');
            } catch (\LogicException $e) {
                $this->assertStringContainsString('read-only', $e->getMessage());
            }
        }

        $this->assertSame([], $http->requests, 'no request may leave the client');
    }

    public function testInvoiceSetPaidSendsPutWithPaidFlag(): void
    {
        $http = (new MockHttpClient())->push(200, ['id' => 'inv1', 'paid' => true]);
        $invoice = $this->makeClient($http)->outboundInvoices->setPaid('inv1', true);

        $request = $http->lastRequest();
        $this->assertInstanceOf(OutboundInvoice::class, $invoice);
        $this->assertSame('PUT', $request->getMethod());
        $this->assertStringContainsString('/outbound-invoices/inv1/set-paid', (string) $request->getUri());
        $this->assertSame(['paid' => true], $this->bodyOf($request));
    }

    public function testInvoiceCancelSendsPost(): void
    {
        $http = (new MockHttpClient())->push(200, ['id' => 'inv1', 'credit_note' => true]);
        $invoice = $this->makeClient($http)->outboundInvoices->cancel('inv1');

        $this->assertTrue($invoice->isCreditNote());
        $this->assertSame('POST', $http->lastRequest()->getMethod());
        $this->assertStringContainsString('/outbound-invoices/inv1/cancel', (string) $http->lastRequest()->getUri());
    }

    public function testArticleVariantsAreASubCollection(): void
    {
        $http = (new MockHttpClient())
            ->push(200, [
                'data' => [
                    ['id' => 'v1', 'label' => 'Premium', 'price' => 250.0, 'tax_percent' => null],
                    ['id' => 'v2', 'label' => 'Basis', 'price' => null, 'tax_percent' => null],
                ],
                'total' => 2, 'limit' => 100, 'offset' => 0,
            ])
            ->push(201, ['id' => 'v3', 'label' => 'Klein', 'price' => 0.0])
            ->push(200, ['id' => 'v1', 'label' => 'Premium', 'price' => null]);

        $client = $this->makeClient($http);

        $page = $client->articles->variants('a1');
        $this->assertCount(2, $page);
        $this->assertContainsOnlyInstancesOf(ArticleVariant::class, iterator_to_array($page));
        $this->assertStringEndsWith('/articles/a1/variants', explode('?', (string) $http->requests[0]->getUri())[0]);
        // The whole point of the model: an inherited field stays null and a 0
        // override stays 0. Neither may be folded into the other.
        $this->assertNull(iterator_to_array($page)[1]->price);

        $created = $client->articles->createVariant('a1', ['label' => 'Klein', 'price' => 0.0]);
        $this->assertSame('POST', $http->requests[1]->getMethod());
        // JSON gives a whole 0.0 back as an int, so compare loosely - what is
        // under test is that it is a VALUE and not null.
        $this->assertNotNull($created->price);
        $this->assertEquals(0.0, $created->price);

        $client->articles->updateVariant('a1', 'v1', ['price' => null]);
        $this->assertSame('PUT', $http->requests[2]->getMethod());
        $this->assertStringEndsWith('/articles/a1/variants/v1', explode('?', (string) $http->requests[2]->getUri())[0]);
        $this->assertSame(['price' => null], $this->bodyOf($http->requests[2]));
    }

    /**
     * `PUT {status: 'lost'}` without `lost_reason` was silently accepted until
     * 2026-08-29; it now answers 422 `lost_reason_required` and writes nothing.
     * A caller must send one of the four reasons in the same request.
     */
    public function testLosingAQuoteWithoutAReasonIsRefused(): void
    {
        $http = (new MockHttpClient())
            ->push(422, ['error' => ['code' => 422, 'message' => 'A reason is required.', 'key' => 'lost_reason_required']]);

        try {
            $this->makeClient($http)->quotes->update('q1', ['status' => 'lost']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('lost_reason_required', $e->getErrorKey());
        }

        $this->assertSame(['status' => 'lost'], $this->bodyOf($http->lastRequest()));
    }

    /**
     * With a valid reason the same call goes through, and the reason is
     * readable back on the model.
     */
    public function testLosingAQuoteWithAReasonSucceeds(): void
    {
        $http = (new MockHttpClient())
            ->push(200, ['id' => 'q1', 'status' => 'lost', 'lost_reason' => 'price']);

        $quote = $this->makeClient($http)->quotes->update('q1', ['status' => 'lost', 'lost_reason' => 'price']);

        $this->assertSame('lost', $quote->status);
        $this->assertSame('price', $quote->lost_reason);
        $this->assertSame(['status' => 'lost', 'lost_reason' => 'price'], $this->bodyOf($http->lastRequest()));
    }

    /**
     * Marking a quote won can create an order and send its confirmation; the
     * response then carries `order_confirmation` alongside the quote itself.
     */
    public function testWinningAQuoteCanReportAnOrderConfirmation(): void
    {
        $http = (new MockHttpClient())->push(200, [
            'id' => 'q1',
            'status' => 'won',
            'order' => ['id' => 'o1', 'label' => 'Website relaunch', 'public_index' => 'A-2026-0007'],
            'order_confirmation' => ['sent' => true, 'error' => ''],
        ]);

        $quote = $this->makeClient($http)->quotes->update('q1', ['status' => 'won']);

        $this->assertSame('won', $quote->status);
        $this->assertSame('o1', $quote->order['id']);
        $this->assertSame('A-2026-0007', $quote->order['public_index']);
        $this->assertTrue($quote->order_confirmation['sent']);
    }

    /**
     * A quote is immutable once issued (409 `quote_issued_locked`) or closed
     * (409 `quote_closed_locked`), and only a draft can still be deleted (409
     * `quote_closed_undeletable`) - all three surface as ConflictException,
     * exactly like any other 409 this client already maps.
     */
    public function testClosedQuoteLocksSurfaceAsConflictException(): void
    {
        $cases = [
            'quote_issued_locked' => 'This quote has already been issued.',
            'quote_closed_locked' => 'This quote has already been won.',
        ];
        foreach ($cases as $key => $message) {
            $http = (new MockHttpClient())
                ->push(409, ['error' => ['code' => 409, 'message' => $message, 'key' => $key]]);
            try {
                $this->makeClient($http)->quotes->update('q1', ['terms_text' => 'New wording']);
                $this->fail("Expected ConflictException for $key");
            } catch (ConflictException $e) {
                $this->assertSame($key, $e->getErrorKey());
            }
        }

        $http = (new MockHttpClient())
            ->push(409, ['error' => ['code' => 409, 'message' => 'Only a draft can be deleted.', 'key' => 'quote_closed_undeletable']]);
        try {
            $this->makeClient($http)->quotes->delete('q1');
            $this->fail('Expected ConflictException for quote_closed_undeletable');
        } catch (ConflictException $e) {
            $this->assertSame('quote_closed_undeletable', $e->getErrorKey());
        }
    }

    /**
     * `version` only advances by issuing a revision in the portal; sending a
     * value below the quote's current one is refused rather than silently
     * hiding a revision that already went out.
     */
    public function testSendingAnOlderVersionIsRefused(): void
    {
        $http = (new MockHttpClient())
            ->push(422, ['error' => ['code' => 422, 'message' => 'The quote version cannot move backwards.', 'key' => 'quote_version_backwards']]);

        try {
            $this->makeClient($http)->quotes->update('q1', ['version' => 1]);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('quote_version_backwards', $e->getErrorKey());
        }
    }

    /**
     * `OutboundInvoice.order_id` replaced `project_id` on 2026-08-28. This is
     * just the read side of the rename - the API neither reads nor emits
     * `project_id` on this entity any more.
     */
    public function testOutboundInvoiceExposesOrderIdInPlaceOfProjectId(): void
    {
        $http = (new MockHttpClient())->push(200, ['id' => 'inv1', 'order_id' => 'o1']);
        $invoice = $this->makeClient($http)->outboundInvoices->get('inv1');

        $this->assertSame('o1', $invoice->order_id);
    }
}
