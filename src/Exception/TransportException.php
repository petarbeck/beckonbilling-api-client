<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Exception;

/**
 * The request never produced an HTTP response - a DNS, TLS, connection or
 * timeout failure raised by the underlying PSR-18 client. The status code is
 * 0; the original PSR-18 exception is the `previous` throwable.
 */
final class TransportException extends ApiException
{
}
