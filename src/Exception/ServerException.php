<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Exception;

/**
 * 5xx - the API failed to process an otherwise valid request. Safe to retry
 * idempotent calls with backoff.
 */
final class ServerException extends ApiException
{
}
