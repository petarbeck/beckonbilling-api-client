<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Tests\Unit;

use BeckonBilling\ApiClient\Exception\AuthenticationException;
use BeckonBilling\ApiClient\Tests\Support\ClientTestCase;
use BeckonBilling\ApiClient\Tests\Support\MockHttpClient;

final class AuthTest extends ClientTestCase
{
    public function testLoginPostsCredentialsWithoutOrganisationParam(): void
    {
        $http = (new MockHttpClient())->push(200, [
            'token' => 'bbp_new',
            'expires_at' => '2026-08-24T00:00:00+02:00',
            'user' => ['id' => 'u1'],
            'organisations' => [['id' => 'org-1']],
        ]);

        $session = $this->makeClient($http)->auth->login('user@example.com', 'secret', ['remember_device' => true]);

        $request = $http->lastRequest();
        $this->assertStringEndsWith('/api/v1/auth/login', explode('?', (string) $request->getUri())[0]);
        $this->assertArrayNotHasKey('organisation', $this->queryOf($request));
        $this->assertSame(
            ['email' => 'user@example.com', 'password' => 'secret', 'remember_device' => true],
            $this->bodyOf($request),
        );
        $this->assertSame('bbp_new', $session['token']);
    }

    public function testTfaRequiredSurfacesAsAuthenticationExceptionWithKey(): void
    {
        $http = (new MockHttpClient())->push(401, ['error' => ['code' => 401, 'message' => '2FA required', 'key' => 'tfa_required']]);

        try {
            $this->makeClient($http)->auth->login('user@example.com', 'secret');
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            $this->assertSame('tfa_required', $e->getErrorKey());
        }
    }

    public function testMeSendsGetWithoutOrganisation(): void
    {
        $http = (new MockHttpClient())->push(200, ['user' => ['id' => 'u1'], 'organisations' => []]);
        $this->makeClient($http)->auth->me();

        $request = $http->lastRequest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertStringEndsWith('/api/v1/auth/me', (string) $request->getUri());
    }

    public function testRegisterPosts(): void
    {
        $http = (new MockHttpClient())->push(200, ['message' => 'ok']);
        $this->makeClient($http)->auth->register(['email' => 'new@example.com', 'name' => 'New User']);

        $request = $http->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/api/v1/auth/register', explode('?', (string) $request->getUri())[0]);
    }
}
