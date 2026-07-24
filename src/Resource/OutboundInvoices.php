<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Resource;

use BeckonBilling\ApiClient\Model\OutboundInvoice;

/**
 * Outbound invoices - `/api/v1/outbound-invoices` (feature: `outbound_invoices`).
 *
 * @method OutboundInvoice get(string $id, array $options = [])
 * @method OutboundInvoice create(array $data, array $options = [])
 * @method OutboundInvoice update(string $id, array $data, array $options = [])
 * @method \BeckonBilling\ApiClient\Collection<OutboundInvoice> list(array $query = [], array $options = [])
 * @method \Generator<int,OutboundInvoice> autoPaging(array $query = [], array $options = [])
 */
final class OutboundInvoices extends AbstractResource
{
    protected function path(): string
    {
        return 'outbound-invoices';
    }

    protected function modelClass(): string
    {
        return OutboundInvoice::class;
    }

    /**
     * Issue the draft: assigns the number, exports the PDF and auto-sends it to
     * the customer. Requires `outbound_invoices` Full **and** the `send`
     * capability. A `send_error` field on the result means mailing failed.
     *
     * @param array<string,mixed> $data     e.g. ['document_ids' => [...]].
     * @param array<string,mixed> $options
     */
    public function issue(string $id, array $data = [], array $options = []): OutboundInvoice
    {
        $options['json'] = $data;
        $response = $this->transport->request('POST', $this->itemPath($id) . '/issue', $options);

        return new OutboundInvoice($response);
    }

    /**
     * (Re-)send an issued invoice to the customer. Requires `outbound_invoices`
     * Full **and** the `send` capability.
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed> $options
     */
    public function send(string $id, array $data = [], array $options = []): OutboundInvoice
    {
        $options['json'] = $data;
        $response = $this->transport->request('POST', $this->itemPath($id) . '/send', $options);

        return new OutboundInvoice($response);
    }

    /**
     * Cancel an issued invoice (creates the credit note). Requires
     * `outbound_invoices` Full.
     *
     * @param array<string,mixed> $options
     */
    public function cancel(string $id, array $options = []): OutboundInvoice
    {
        $response = $this->transport->request('POST', $this->itemPath($id) . '/cancel', $options);

        return new OutboundInvoice($response);
    }

    /**
     * Record or remove an untracked payment over the open amount. Requires
     * `outbound_invoices` Full **and** the `bank` capability. `false` is
     * refused (409) while transaction-linked payments exist.
     *
     * @param array<string,mixed> $options
     */
    public function setPaid(string $id, bool $paid, array $options = []): OutboundInvoice
    {
        $options['json'] = ['paid' => $paid];
        $response = $this->transport->request('PUT', $this->itemPath($id) . '/set-paid', $options);

        return new OutboundInvoice($response);
    }

    /**
     * Download the final PDF (409 for drafts). Returns the raw PDF bytes.
     *
     * @param array<string,mixed> $options
     */
    public function pdf(string $id, array $options = []): string
    {
        $options['raw'] = true;

        return $this->transport->request('GET', $this->itemPath($id) . '/pdf', $options);
    }
}
