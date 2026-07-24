<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Exception;

/**
 * 403 - the token lacks the required feature level or capability. Inspect
 * {@see ApiException::getErrorKey()} for the specific reason, e.g.
 * `missing_permission`, `send_not_permitted`, `bank_not_permitted`.
 */
final class PermissionException extends ApiException
{
}
