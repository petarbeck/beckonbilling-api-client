<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Tests\Unit;

use BeckonBilling\ApiClient\Exception\AuthenticationException;
use BeckonBilling\ApiClient\Exception\ConflictException;
use BeckonBilling\ApiClient\Exception\NotFoundException;
use BeckonBilling\ApiClient\Exception\PermissionException;
use BeckonBilling\ApiClient\Exception\ServerException;
use BeckonBilling\ApiClient\Exception\TransportException;
use BeckonBilling\ApiClient\Exception\ValidationException;
use BeckonBilling\ApiClient\Tests\Support\ClientTestCase;
use BeckonBilling\ApiClient\Tests\Support\MockHttpClient;
use BeckonBilling\ApiClient\Tests\Support\MockNetworkException;

final class TransportTest extends ClientTestCase
{
    public function testSendsBearerTokenAndAcceptAndUserAgent(): void
    {
        $http = (new MockHttpClient())->push(200, ['id' => 'c1']);
        $this->makeClient($http)->customers->get('c1');

        $request = $http->lastRequest();
        $this->assertSame('Bearer bbp_test', $request->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
        $this->assertStringStartsWith('beckonbilling-php/', $request->getHeaderLine('User-Agent'));
    }

    public function testAppendsDefaultOrganisationToQuery(): void
    {
        $http = (new MockHttpClient())->push(200, ['id' => 'c1']);
        $this->makeClient($http)->customers->get('c1');

        $this->assertSame('org-uuid', $this->queryOf($http->lastRequest())['organisation'] ?? null);
    }

    public function testPerCallOrganisationOverride(): void
    {
        $http = (new MockHttpClient())->push(200, ['id' => 'c1']);
        $this->makeClient($http)->customers->get('c1', ['organisation' => 'other-org']);

        $this->assertSame('other-org', $this->queryOf($http->lastRequest())['organisation'] ?? null);
    }

    public function testNullOrganisationOmitsParam(): void
    {
        $http = (new MockHttpClient())->push(200, ['id' => 'c1']);
        $this->makeClient($http)->customers->get('c1', ['organisation' => null]);

        $this->assertArrayNotHasKey('organisation', $this->queryOf($http->lastRequest()));
    }

    public function testTargetsApiV1PathAndItemId(): void
    {
        $http = (new MockHttpClient())->push(200, ['id' => 'c 1/x']);
        $this->makeClient($http)->customers->get('c 1/x');

        $uri = (string) $http->lastRequest()->getUri();
        $this->assertStringContainsString('/api/v1/customers/c%201%2Fx', $uri);
    }

    public function testSendsJsonBodyWithContentType(): void
    {
        $http = (new MockHttpClient())->push(200, ['id' => 'c1']);
        $this->makeClient($http)->customers->create(['label' => 'ACME']);

        $request = $http->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame(['label' => 'ACME'], $this->bodyOf($request));
    }

    public function testCastsBooleanQueryFiltersToIntegers(): void
    {
        $http = (new MockHttpClient())->push(200, ['data' => [], 'total' => 0, 'limit' => 100, 'offset' => 0]);
        $this->makeClient($http)->outboundInvoices->list(['paid' => false, 'sent' => true]);

        $query = $this->queryOf($http->lastRequest());
        $this->assertSame('0', $query['paid']);
        $this->assertSame('1', $query['sent']);
    }

    public function testRawPdfReturnsBytes(): void
    {
        $http = (new MockHttpClient())->push(200, '%PDF-1.7 binary', ['Content-Type' => 'application/pdf']);
        $pdf = $this->makeClient($http)->outboundInvoices->pdf('inv1');

        $this->assertSame('%PDF-1.7 binary', $pdf);
        $this->assertSame('application/pdf', $http->lastRequest()->getHeaderLine('Accept'));
    }

    public function testMapsErrorStatusesToTypedExceptions(): void
    {
        $cases = [
            401 => AuthenticationException::class,
            403 => PermissionException::class,
            404 => NotFoundException::class,
            409 => ConflictException::class,
            422 => ValidationException::class,
            500 => ServerException::class,
        ];

        foreach ($cases as $status => $expected) {
            $http = (new MockHttpClient())->push($status, ['error' => ['code' => $status, 'message' => 'nope', 'key' => 'some_key']]);
            try {
                $this->makeClient($http)->customers->get('c1');
                $this->fail("Expected $expected for status $status");
            } catch (\BeckonBilling\ApiClient\Exception\ApiException $e) {
                $this->assertInstanceOf($expected, $e);
                $this->assertSame($status, $e->getStatusCode());
                $this->assertSame('some_key', $e->getErrorKey());
                $this->assertSame('nope', $e->getMessage());
            }
        }
    }

    public function testCapabilityDenialExposesErrorKey(): void
    {
        $http = (new MockHttpClient())->push(403, ['error' => ['code' => 403, 'message' => 'send not permitted', 'key' => 'send_not_permitted']]);

        try {
            $this->makeClient($http)->outboundInvoices->issue('inv1');
            $this->fail('Expected PermissionException');
        } catch (PermissionException $e) {
            $this->assertSame('send_not_permitted', $e->getErrorKey());
        }
    }

    public function testNetworkFailureBecomesTransportException(): void
    {
        $http = (new MockHttpClient())->pushThrowable(new MockNetworkException('boom'));

        $this->expectException(TransportException::class);
        $this->makeClient($http)->customers->get('c1');
    }
}
