<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Resource;

use BeckonBilling\ApiClient\Exception\GoneException;
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
     * @deprecated Since 0.14.0. The route this called, `POST
     * /quotes/{id}/convert`, was retired on 2026-08-28 and now answers 410
     * `quote_conversion_moved` on every call - a won quote becomes an ORDER
     * first, and the order is what gets invoiced. That order is not reachable
     * through this API yet, so there is no direct replacement call here; the
     * portal is the only place to take a won quote to invoice right now.
     *
     * This method is kept, rather than removed, so an existing call site
     * fails with a clear, catchable, on-topic exception instead of a fatal
     * "call to undefined method" - the same reasoning
     * {@see \BeckonBilling\ApiClient\Resource\ReadOnlyResource} uses for
     * `create`/`update`/`delete` on a read-only collection. It throws
     * LOCALLY, without a request, because the outcome is already known for
     * every input: unlike a read-only collection's 405 (a property of the
     * resource, so `\LogicException` fits), this is a specific, versioned API
     * state with a stable `error.key` a caller may already branch on - so it
     * is raised as a {@see \BeckonBilling\ApiClient\Exception\GoneException},
     * an `ApiException` a `catch (ApiException $e) { match ($e->getErrorKey())
     * ... }` block handles exactly as if the server had answered it.
     *
     * @param array<string,mixed> $options Unused; kept for signature compatibility.
     * @throws GoneException Always - this method never returns normally.
     */
    public function convert(string $id, ?string $scope = null, array $options = []): array
    {
        throw new GoneException(
            'POST /quotes/{id}/convert was retired on 2026-08-28 and now answers 410 on every '
            . 'call. A won quote becomes an order, and the order is what gets invoiced - there is '
            . 'no /api/v1 route for orders yet, so this client has no replacement call to make. '
            . 'Use the portal to invoice a won quote until an order route ships.',
            410,
            'quote_conversion_moved'
        );
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
