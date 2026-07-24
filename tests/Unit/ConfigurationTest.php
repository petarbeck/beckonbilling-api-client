<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Tests\Unit;

use BeckonBilling\ApiClient\Configuration;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    private function factories(): array
    {
        $factory = new Psr17Factory();

        return [
            'http_client' => new \BeckonBilling\ApiClient\Tests\Support\MockHttpClient(),
            'request_factory' => $factory,
            'stream_factory' => $factory,
        ];
    }

    public function testAppendsApiV1ToBaseUri(): void
    {
        $config = new Configuration(['base_uri' => 'https://portal.example.com/', 'token' => 'bbp_x'] + $this->factories());
        $this->assertSame('https://portal.example.com/api/v1', $config->baseUri);
    }

    public function testDoesNotDoubleApiV1WhenAlreadyPresent(): void
    {
        $config = new Configuration(['base_uri' => 'https://portal.example.com/api/v1', 'token' => 'bbp_x'] + $this->factories());
        $this->assertSame('https://portal.example.com/api/v1', $config->baseUri);
    }

    public function testMissingBaseUriThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Configuration(['token' => 'bbp_x'] + $this->factories());
    }

    public function testNonHttpBaseUriThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Configuration(['base_uri' => 'portal.example.com', 'token' => 'bbp_x'] + $this->factories());
    }

    public function testEmptyTokenThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Configuration(['base_uri' => 'https://portal.example.com', 'token' => ''] + $this->factories());
    }

    public function testTokenIsOptional(): void
    {
        $config = new Configuration(['base_uri' => 'https://portal.example.com'] + $this->factories());
        $this->assertNull($config->token);
    }

    public function testDefaultUserAgent(): void
    {
        $config = new Configuration(['base_uri' => 'https://portal.example.com', 'token' => 'bbp_x'] + $this->factories());
        $this->assertStringStartsWith('beckonbilling-php/', $config->userAgent);
    }

    public function testDiscoversHttpClientWhenNotProvided(): void
    {
        // guzzle + nyholm are dev dependencies, so discovery must succeed.
        $config = new Configuration(['base_uri' => 'https://portal.example.com', 'token' => 'bbp_x']);
        $this->assertInstanceOf(\Psr\Http\Client\ClientInterface::class, $config->httpClient());
        $this->assertInstanceOf(\Psr\Http\Message\RequestFactoryInterface::class, $config->requestFactory());
        $this->assertInstanceOf(\Psr\Http\Message\StreamFactoryInterface::class, $config->streamFactory());
    }
}
