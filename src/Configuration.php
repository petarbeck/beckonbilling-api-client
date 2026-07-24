<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient;

use Http\Discovery\Exception\NotFoundException as DiscoveryNotFound;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Immutable client configuration.
 *
 * Accepts an options array:
 *   - token             string  API token (`bbp_...` org token, or a user token). Optional
 *                               only for `auth->login()` / `auth->register()`.
 *   - base_uri          string  The portal host, e.g. `https://portal.beckonbilling.com`.
 *                               `/api/v1` is appended automatically when absent. Required.
 *   - organisation      string  Default organisation UUID applied to every request as
 *                               `?organisation=`. Optional (a single-organisation user token
 *                               may omit it).
 *   - http_client       PSR-18 ClientInterface        Optional; auto-discovered otherwise.
 *   - request_factory   PSR-17 RequestFactoryInterface Optional; auto-discovered otherwise.
 *   - stream_factory    PSR-17 StreamFactoryInterface  Optional; auto-discovered otherwise.
 *   - user_agent        string  Optional User-Agent override.
 */
final class Configuration
{
    public const VERSION = '0.1.0';

    public readonly ?string $token;
    public readonly string $baseUri;
    public readonly ?string $organisation;
    public readonly string $userAgent;

    private readonly ClientInterface $httpClient;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;

    /**
     * @param array<string,mixed> $options
     */
    public function __construct(array $options)
    {
        $token = $options['token'] ?? null;
        if ($token !== null && (!is_string($token) || $token === '')) {
            throw new \InvalidArgumentException('"token" must be a non-empty string when provided.');
        }
        $this->token = $token;

        $baseUri = $options['base_uri'] ?? null;
        if (!is_string($baseUri) || $baseUri === '') {
            throw new \InvalidArgumentException('"base_uri" is required, e.g. "https://portal.beckonbilling.com".');
        }
        if (!preg_match('#^https?://#i', $baseUri)) {
            throw new \InvalidArgumentException('"base_uri" must be an absolute http(s) URL.');
        }
        $this->baseUri = $this->normaliseBaseUri($baseUri);

        $organisation = $options['organisation'] ?? null;
        if ($organisation !== null && (!is_string($organisation) || $organisation === '')) {
            throw new \InvalidArgumentException('"organisation" must be a non-empty UUID string when provided.');
        }
        $this->organisation = $organisation;

        $userAgent = $options['user_agent'] ?? null;
        $this->userAgent = is_string($userAgent) && $userAgent !== ''
            ? $userAgent
            : 'beckonbilling-php/' . self::VERSION;

        $this->httpClient = $this->resolveHttpClient($options['http_client'] ?? null);
        $this->requestFactory = $this->resolveRequestFactory($options['request_factory'] ?? null);
        $this->streamFactory = $this->resolveStreamFactory($options['stream_factory'] ?? null);
    }

    public function httpClient(): ClientInterface
    {
        return $this->httpClient;
    }

    public function requestFactory(): RequestFactoryInterface
    {
        return $this->requestFactory;
    }

    public function streamFactory(): StreamFactoryInterface
    {
        return $this->streamFactory;
    }

    private function normaliseBaseUri(string $baseUri): string
    {
        $base = rtrim($baseUri, '/');
        if (!str_ends_with($base, '/api/v1')) {
            $base .= '/api/v1';
        }

        return $base;
    }

    private function resolveHttpClient(mixed $client): ClientInterface
    {
        if ($client instanceof ClientInterface) {
            return $client;
        }
        if ($client !== null) {
            throw new \InvalidArgumentException('"http_client" must implement Psr\Http\Client\ClientInterface.');
        }

        try {
            return Psr18ClientDiscovery::find();
        } catch (DiscoveryNotFound $e) {
            throw new \RuntimeException(
                'No PSR-18 HTTP client was found. Install one (e.g. "composer require guzzlehttp/guzzle") '
                . 'or pass a "http_client" in the configuration.',
                0,
                $e,
            );
        }
    }

    private function resolveRequestFactory(mixed $factory): RequestFactoryInterface
    {
        if ($factory instanceof RequestFactoryInterface) {
            return $factory;
        }
        if ($factory !== null) {
            throw new \InvalidArgumentException('"request_factory" must implement Psr\Http\Message\RequestFactoryInterface.');
        }

        try {
            return Psr17FactoryDiscovery::findRequestFactory();
        } catch (DiscoveryNotFound $e) {
            throw new \RuntimeException(
                'No PSR-17 request factory was found. Install one (e.g. "composer require nyholm/psr7") '
                . 'or pass a "request_factory" in the configuration.',
                0,
                $e,
            );
        }
    }

    private function resolveStreamFactory(mixed $factory): StreamFactoryInterface
    {
        if ($factory instanceof StreamFactoryInterface) {
            return $factory;
        }
        if ($factory !== null) {
            throw new \InvalidArgumentException('"stream_factory" must implement Psr\Http\Message\StreamFactoryInterface.');
        }

        try {
            return Psr17FactoryDiscovery::findStreamFactory();
        } catch (DiscoveryNotFound $e) {
            throw new \RuntimeException(
                'No PSR-17 stream factory was found. Install one (e.g. "composer require nyholm/psr7") '
                . 'or pass a "stream_factory" in the configuration.',
                0,
                $e,
            );
        }
    }
}
