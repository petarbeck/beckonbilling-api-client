<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Resource;

use BeckonBilling\ApiClient\Model\Quote;

/**
 * Quotes - `/api/v1/quotes` (feature: `quotes`).
 *
 * @method Quote get(string $id, array $options = [])
 * @method Quote create(array $data, array $options = [])
 * @method Quote update(string $id, array $data, array $options = [])
 * @method \BeckonBilling\ApiClient\Collection<Quote> list(array $query = [], array $options = [])
 * @method \Generator<int,Quote> autoPaging(array $query = [], array $options = [])
 */
final class Quotes extends AbstractResource
{
    protected function path(): string
    {
        return 'quotes';
    }

    protected function modelClass(): string
    {
        return Quote::class;
    }

    /**
     * Issue a draft (assigns the number). Requires `quotes` Full.
     *
     * @param array<string,mixed> $options
     */
    public function issue(string $id, array $options = []): Quote
    {
        $response = $this->transport->request('POST', $this->itemPath($id) . '/issue', $options);

        return new Quote($response);
    }

    /**
     * Email the quote PDF to the recipient (issues drafts first). Requires
     * `quotes` Full **and** the `send` capability.
     *
     * The API wraps this one: the body is `{ sent_to, quote, ... }`, not a bare
     * quote. Until 0.9.0 the whole envelope was hydrated as the Quote, so every
     * field of the returned model was null and the quote sat one level down at
     * `->quote`. The envelope is still reachable via `sendResult()`.
     *
     * @param array<string,mixed> $data     e.g. ['document_ids' => [...]] to override attachments.
     * @param array<string,mixed> $options
     */
    public function send(string $id, array $data = [], array $options = []): Quote
    {
        return $this->sendResult($id, $data, $options)['quote'] ?? new Quote([]);
    }

    /**
     * The full send response: the address it went to, the quote, and - only
     * when it happened - `detached_positions`, the lines whose retired catalog
     * reference was dropped as the draft was issued. No figure changes when
     * that happens, but it is reported rather than done silently.
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed> $options
     * @return array{sent_to: ?string, quote: ?Quote, detached_positions: array<int,mixed>}
     */
    public function sendResult(string $id, array $data = [], array $options = []): array
    {
        $options['json'] = $data;
        $response = $this->transport->request('POST', $this->itemPath($id) . '/send', $options);

        return [
            'sent_to' => isset($response['sent_to']) ? (string) $response['sent_to'] : null,
            'quote' => is_array($response['quote'] ?? null) ? new Quote($response['quote']) : null,
            'detached_positions' => is_array($response['detached_positions'] ?? null)
                ? $response['detached_positions']
                : [],
        ];
    }

    /**
     * Create a draft invoice from the quote. Requires `quotes` Full and
     * `outbound_invoices` Full.
     *
     * The API answers 201 with **`outbound_invoice_id`**. This method read
     * `invoice_id` until 0.9.0 - a key the API has never sent - so the id came
     * back null on every successful conversion. `invoice_id` is still returned
     * as an alias so nothing that read it breaks, but it now carries the real
     * value; prefer `outbound_invoice_id`.
     *
     * @param array<string,mixed> $options
     * @return array{outbound_invoice_id: ?string, invoice_id: ?string, quote: ?Quote}
     */
    public function convert(string $id, array $options = []): array
    {
        $response = $this->transport->request('POST', $this->itemPath($id) . '/convert', $options);
        $quote = is_array($response['quote'] ?? null) ? new Quote($response['quote']) : null;

        $invoiceId = $response['outbound_invoice_id'] ?? $response['invoice_id'] ?? null;
        $invoiceId = null === $invoiceId ? null : (string) $invoiceId;

        return [
            'outbound_invoice_id' => $invoiceId,
            // Deprecated alias, kept so an existing caller keeps working.
            'invoice_id' => $invoiceId,
            'quote' => $quote,
        ];
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
