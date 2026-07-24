<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Http;

use BeckonBilling\ApiClient\Configuration;
use BeckonBilling\ApiClient\Exception\ApiException;
use BeckonBilling\ApiClient\Exception\TransportException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The single point through which every request passes: it builds the PSR-7
 * request (base URI, `/api/v1`, `?organisation=`, auth + JSON headers), sends
 * it through the configured PSR-18 client, then decodes the JSON envelope or
 * maps a failure onto the right {@see ApiException} subclass.
 *
 * @internal Consumers use the resource classes on {@see \BeckonBilling\ApiClient\Client}.
 */
final class Transport
{
    public function __construct(private readonly Configuration $config)
    {
    }

    public function configuration(): Configuration
    {
        return $this->config;
    }

    /**
     * @param array{
     *     query?: array<string,mixed>,
     *     json?: array<string,mixed>|null,
     *     organisation?: string|null,
     *     raw?: bool,
     *     accept?: string
     * } $options
     *
     * @return ($options is array{raw: true} ? string : array<string,mixed>)
     */
    public function request(string $method, string $path, array $options = []): array|string
    {
        $raw = ($options['raw'] ?? false) === true;
        $accept = $options['accept'] ?? ($raw ? 'application/pdf' : 'application/json');

        $uri = $this->buildUri($path, $options['query'] ?? [], $options);
        $request = $this->config->requestFactory()->createRequest($method, $uri)
            ->withHeader('Accept', $accept)
            ->withHeader('User-Agent', $this->config->userAgent);

        if ($this->config->token !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $this->config->token);
        }

        if (array_key_exists('json', $options) && $options['json'] !== null) {
            $payload = json_encode($options['json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($payload === false) {
                throw new \InvalidArgumentException('Request body could not be JSON-encoded: ' . json_last_error_msg());
            }
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->config->streamFactory()->createStream($payload));
        }

        try {
            $response = $this->config->httpClient()->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TransportException(
                'HTTP request failed: ' . $e->getMessage(),
                0,
                null,
                null,
                null,
                $e,
            );
        }

        return $this->handle($response, $raw);
    }

    private function handle(ResponseInterface $response, bool $raw): array|string
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($status >= 200 && $status < 300) {
            if ($raw) {
                return $body;
            }
            if ($body === '') {
                return [];
            }
            $decoded = $this->decode($body, $response);

            return $decoded;
        }

        // Error path: decode best-effort, then map to a typed exception.
        $decoded = null;
        if ($body !== '') {
            try {
                $decoded = $this->decode($body, $response);
            } catch (ApiException) {
                $decoded = null;
            }
        }

        throw ApiException::fromResponse($response, $decoded);
    }

    /**
     * @return array<string,mixed>
     */
    private function decode(string $body, ResponseInterface $response): array
    {
        /** @var mixed $decoded */
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new ApiException(
                'Expected a JSON object in the response body but could not decode it.',
                $response->getStatusCode(),
                null,
                null,
                $response,
            );
        }

        return $decoded;
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed> $options
     */
    private function buildUri(string $path, array $query, array $options): string
    {
        $uri = $this->config->baseUri . '/' . ltrim($path, '/');

        $organisation = array_key_exists('organisation', $options)
            ? $options['organisation']
            : $this->config->organisation;
        if (is_string($organisation) && $organisation !== '') {
            $query['organisation'] = $organisation;
        }

        $query = $this->normaliseQuery($query);
        if ($query !== []) {
            $uri .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $uri;
    }

    /**
     * Cast booleans to the `0`/`1` the API filters expect and drop null values.
     *
     * @param array<string,mixed> $query
     * @return array<string,int|string>
     */
    private function normaliseQuery(array $query): array
    {
        $out = [];
        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_bool($value)) {
                $out[$key] = $value ? 1 : 0;
                continue;
            }
            $out[$key] = is_int($value) ? $value : (string) $value;
        }

        return $out;
    }
}
