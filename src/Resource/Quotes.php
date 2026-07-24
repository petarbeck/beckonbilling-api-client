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
     * @param array<string,mixed> $data     e.g. ['document_ids' => [...]] to override attachments.
     * @param array<string,mixed> $options
     */
    public function send(string $id, array $data = [], array $options = []): Quote
    {
        $options['json'] = $data;
        $response = $this->transport->request('POST', $this->itemPath($id) . '/send', $options);

        return new Quote($response);
    }

    /**
     * Create a draft invoice from the quote. Requires `quotes` Full and
     * `outbound_invoices` Full.
     *
     * @param array<string,mixed> $options
     * @return array{invoice_id: ?string, quote: ?Quote}
     */
    public function convert(string $id, array $options = []): array
    {
        $response = $this->transport->request('POST', $this->itemPath($id) . '/convert', $options);
        $quote = is_array($response['quote'] ?? null) ? new Quote($response['quote']) : null;

        return [
            'invoice_id' => isset($response['invoice_id']) ? (string) $response['invoice_id'] : null,
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
