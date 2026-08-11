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
     * capability.
     *
     * Two side effects are best-effort and never fail the issue, so CHECK BOTH
     * on the result: `send_error` means the mail did not go out, and
     * `payment_link_error` means the invoice offers online payment with no
     * Stripe link behind it. Present only when they happened.
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
     * Cancel an issued invoice. Requires `outbound_invoices` Full.
     *
     * Returns the **credit note that was created**, not the invoice that was
     * cancelled - read `cancels_outbound_invoice_id` on it to name the other
     * half. A cancellation of an invoice that never received money settles that
     * credit note automatically; one that was paid even in part leaves it open,
     * because that is a real refund owed to the customer.
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
