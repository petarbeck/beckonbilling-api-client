<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Exception;

/**
 * 401 - the token is missing, invalid, expired or revoked, or two-factor
 * authentication is required (`error.key` = `tfa_required`) on `auth/login`.
 */
final class AuthenticationException extends ApiException
{
}
