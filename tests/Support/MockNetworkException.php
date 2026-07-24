<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Tests\Support;

use Psr\Http\Client\ClientExceptionInterface;

/**
 * A PSR-18 client-level exception, used to simulate a network failure.
 */
final class MockNetworkException extends \RuntimeException implements ClientExceptionInterface
{
}
