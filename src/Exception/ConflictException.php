<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Exception;

/**
 * 409 - the request conflicts with the resource's current state, e.g.
 * deleting an issued invoice (cancel it instead), downloading a draft PDF,
 * or removing the paid mark while transaction-linked payments exist.
 */
final class ConflictException extends ApiException
{
}
