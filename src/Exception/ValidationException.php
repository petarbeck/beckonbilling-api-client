<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Exception;

/**
 * 400 / 422 - the request payload was rejected (missing/invalid fields).
 * The specific reason is in {@see ApiException::getErrorKey()}.
 */
final class ValidationException extends ApiException
{
}
