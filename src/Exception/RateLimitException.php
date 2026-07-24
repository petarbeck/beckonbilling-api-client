<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Exception;

/**
 * 429 - too many requests. Back off and retry.
 */
final class RateLimitException extends ApiException
{
}
