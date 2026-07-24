<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A deterministic PSR-18 client: it records every request and replays a queue
 * of canned responses (or throwables), so the whole client can be exercised
 * without any network access.
 */
final class MockHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<ResponseInterface|\Throwable> */
    private array $queue = [];

    private Psr17Factory $factory;

    public function __construct()
    {
        $this->factory = new Psr17Factory();
    }

    /**
     * Queue a JSON (array) or raw (string) response.
     *
     * @param array<string,mixed>|string $body
     * @param array<string,string>       $headers
     */
    public function push(int $status, array|string $body = [], array $headers = []): self
    {
        $payload = is_string($body) ? $body : (string) json_encode($body);
        $response = $this->factory->createResponse($status);
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        $this->queue[] = $response->withBody($this->factory->createStream($payload));

        return $this;
    }

    public function pushThrowable(\Throwable $throwable): self
    {
        $this->queue[] = $throwable;

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        $next = array_shift($this->queue);
        if ($next === null) {
            throw new \RuntimeException('MockHttpClient: no queued response for ' . $request->getMethod() . ' ' . $request->getUri());
        }
        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    public function lastRequest(): RequestInterface
    {
        $last = $this->requests[array_key_last($this->requests)] ?? null;
        if ($last === null) {
            throw new \RuntimeException('MockHttpClient: no request has been made yet.');
        }

        return $last;
    }
}
