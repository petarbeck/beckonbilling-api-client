<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Tests\Support;

use BeckonBilling\ApiClient\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

abstract class ClientTestCase extends TestCase
{
    protected function makeClient(MockHttpClient $http, array $overrides = []): Client
    {
        $factory = new Psr17Factory();

        return new Client(array_merge([
            'token' => 'bbp_test',
            'base_uri' => 'https://portal.example.com',
            'organisation' => 'org-uuid',
            'http_client' => $http,
            'request_factory' => $factory,
            'stream_factory' => $factory,
        ], $overrides));
    }

    /**
     * @return array<string,string> the request's query params as an assoc array
     */
    protected function queryOf(\Psr\Http\Message\RequestInterface $request): array
    {
        parse_str($request->getUri()->getQuery(), $params);

        /** @var array<string,string> $params */
        return $params;
    }

    protected function bodyOf(\Psr\Http\Message\RequestInterface $request): array
    {
        $decoded = json_decode((string) $request->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }
}
