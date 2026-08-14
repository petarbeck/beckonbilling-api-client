<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Tests\Unit;

use BeckonBilling\ApiClient\Collection;
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

    public function testQuoteIssueSendConvertPdf(): void
    {
        $http = (new MockHttpClient())
            ->push(200, ['id' => 'q1', 'status' => 'issued'])
            // The send response is an ENVELOPE, which is what the API really sends.
            ->push(200, ['sent_to' => 'kunde@example.com', 'quote' => ['id' => 'q1', 'status' => 'issued']])
            ->push(201, ['outbound_invoice_id' => 'inv9', 'quote' => ['id' => 'q1', 'status' => 'converted']])
            ->push(200, '%PDF-quote', ['Content-Type' => 'application/pdf']);

        $client = $this->makeClient($http);

        $issued = $client->quotes->issue('q1');
        $this->assertInstanceOf(Quote::class, $issued);
        $this->assertStringEndsWith('/quotes/q1/issue', explode('?', (string) $http->requests[0]->getUri())[0]);

        $client->quotes->send('q1', ['document_ids' => ['d1']]);
        $this->assertStringEndsWith('/send', explode('?', (string) $http->requests[1]->getUri())[0]);
        $this->assertSame(['document_ids' => ['d1']], $this->bodyOf($http->requests[1]));

        $result = $client->quotes->convert('q1');
        $this->assertSame('inv9', $result['outbound_invoice_id']);
        $this->assertInstanceOf(Quote::class, $result['quote']);

        $pdf = $client->quotes->pdf('q1');
        $this->assertSame('%PDF-quote', $pdf);
    }

    /**
     * The API answers `outbound_invoice_id`; this method read `invoice_id`, a
     * key it has never sent, so a successful conversion handed back null. The
     * old key is kept as an alias, now carrying the real value.
     */
    public function testConvertReadsTheKeyTheApiActuallySends(): void
    {
        $http = (new MockHttpClient())
            ->push(201, ['outbound_invoice_id' => 'inv9', 'quote' => ['id' => 'q1']]);

        $result = $this->makeClient($http)->quotes->convert('q1');

        $this->assertSame('inv9', $result['outbound_invoice_id']);
        $this->assertSame('inv9', $result['invoice_id'], 'the deprecated alias must carry the real id');
    }

    /**
     * A staged conversion has to reach the wire as `scope`, and the default has
     * to stay "no body at all" - a quote converted without a scope must keep
     * billing the whole amount exactly as before.
     */
    public function testConvertSendsTheScopeAndOmitsItByDefault(): void
    {
        $http = (new MockHttpClient())
            ->push(201, ['outbound_invoice_id' => 'inv1', 'quote' => ['id' => 'q1']])
            ->push(201, ['outbound_invoice_id' => 'inv2', 'quote' => ['id' => 'q1']]);

        $client = $this->makeClient($http);

        $client->quotes->convert('q1');
        $this->assertSame([], $this->bodyOf($http->requests[0]), 'no scope must post no body');

        $client->quotes->convert('q1', 'deposit');
        $this->assertSame(['scope' => 'deposit'], $this->bodyOf($http->requests[1]));
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
                    ['id' => 'u1', 'short' => 'h', 'plural' => '', 'label' => 'Stunde'],
                    ['id' => 'u2', 'short' => 'Monat', 'plural' => 'Monate', 'label' => 'Monat'],
                ],
                'total' => 2, 'limit' => 100, 'offset' => 0,
            ])
            ->push(200, ['id' => 'u2', 'short' => 'Monat', 'plural' => 'Monate'])
            ->push(200, [
                'data' => [['id' => 't1', 'kind' => 'invoice', 'days' => 0, 'is_default' => true]],
                'total' => 1, 'limit' => 100, 'offset' => 0,
            ]);

        $client = $this->makeClient($http);

        $units = $client->units->list();
        $this->assertContainsOnlyInstancesOf(Unit::class, iterator_to_array($units));
        $this->assertStringEndsWith('/units', explode('?', (string) $http->requests[0]->getUri())[0]);
        // An empty plural means "does not inflect", not "missing".
        $this->assertSame('', iterator_to_array($units)[0]->plural);
        $this->assertSame('Monate', iterator_to_array($units)[1]->plural);

        $this->assertInstanceOf(Unit::class, $client->units->get('u2'));

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
            fn () => $client->units->create(['short' => 'kg']),
            fn () => $client->units->update('u1', ['short' => 'kg']),
            fn () => $client->units->delete('u1'),
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
}
