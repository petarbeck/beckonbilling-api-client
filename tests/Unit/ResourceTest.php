<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Tests\Unit;

use BeckonBilling\ApiClient\Collection;
use BeckonBilling\ApiClient\Model\Customer;
use BeckonBilling\ApiClient\Model\OutboundInvoice;
use BeckonBilling\ApiClient\Model\Quote;
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
            ->push(200, ['id' => 'q1', 'status' => 'issued'])
            ->push(200, ['invoice_id' => 'inv9', 'quote' => ['id' => 'q1', 'status' => 'converted']])
            ->push(200, '%PDF-quote', ['Content-Type' => 'application/pdf']);

        $client = $this->makeClient($http);

        $issued = $client->quotes->issue('q1');
        $this->assertInstanceOf(Quote::class, $issued);
        $this->assertStringEndsWith('/quotes/q1/issue', explode('?', (string) $http->requests[0]->getUri())[0]);

        $client->quotes->send('q1', ['document_ids' => ['d1']]);
        $this->assertStringEndsWith('/send', explode('?', (string) $http->requests[1]->getUri())[0]);
        $this->assertSame(['document_ids' => ['d1']], $this->bodyOf($http->requests[1]));

        $result = $client->quotes->convert('q1');
        $this->assertSame('inv9', $result['invoice_id']);
        $this->assertInstanceOf(Quote::class, $result['quote']);

        $pdf = $client->quotes->pdf('q1');
        $this->assertSame('%PDF-quote', $pdf);
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
}
