<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Exception;

/**
 * 410 - the route existed and was retired for good; no deprecation window,
 * no successor at this address. Unlike a 404 (never existed, or belongs to
 * another organisation), a 410 means the caller's code is calling something
 * that used to work and stopped working on purpose - `error.key` names which
 * route and, where one exists, points at what replaced it.
 *
 * `POST /quotes/{id}/convert` is the one route currently retired this way
 * (`error.key = quote_conversion_moved`, since 2026-08-28); see
 * {@see \BeckonBilling\ApiClient\Resource\Quotes::convert()}.
 */
final class GoneException extends ApiException
{
}
