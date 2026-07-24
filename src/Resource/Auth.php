<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Resource;

use BeckonBilling\ApiClient\Http\Transport;

/**
 * User-token authentication - `/api/v1/auth/*`.
 *
 * `login()` and `register()` need no token (they mint/activate one); `me()`
 * needs a user token. None of these carry an organisation parameter, so they
 * work on a client configured without one.
 */
final class Auth
{
    public function __construct(private readonly Transport $transport)
    {
    }

    /**
     * Self-registration: `{email, name, language?}` -> pending account +
     * activation email. Returns the API body (may include a `verify_url` in
     * dev when platform SMTP is unset).
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function register(array $data, array $options = []): array
    {
        $options['json'] = $data;
        $options['organisation'] = null;

        return $this->transport->request('POST', 'auth/register', $options);
    }

    /**
     * Sign a user in and mint a user token.
     *
     * On success returns `{token, expires_at, user, organisations:[...]}`. When
     * two-factor authentication is enabled the API answers 401 `tfa_required`
     * (a {@see \BeckonBilling\ApiClient\Exception\AuthenticationException} whose
     * `getErrorKey()` is `tfa_required`) until a `tfa_code` is supplied via
     * `$extra`; pass `remember_device => true` to receive a `device_token`.
     *
     * @param array<string,mixed> $extra    e.g. ['tfa_code' => '123456', 'remember_device' => true, 'device_token' => 'bbd_...'].
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function login(string $email, string $password, array $extra = [], array $options = []): array
    {
        $options['json'] = ['email' => $email, 'password' => $password] + $extra;
        $options['organisation'] = null;

        return $this->transport->request('POST', 'auth/login', $options);
    }

    /**
     * Current user + accessible organisations for a user token (session
     * restore).
     *
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function me(array $options = []): array
    {
        $options['organisation'] = null;

        return $this->transport->request('GET', 'auth/me', $options);
    }
}
