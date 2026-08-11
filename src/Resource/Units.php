<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Resource;

use BeckonBilling\ApiClient\Model\Unit;

/**
 * Units of measure - `/api/v1/units`. **Read-only.**
 *
 * The organisation's unit vocabulary. A position's `unit` must be one of these
 * short forms; anything else is ADOPTED into the vocabulary. So read this
 * before writing positions, and pick a `short` from it.
 *
 * Readable by any token that can VIEW at least one of `articles`, `quotes`,
 * `outbound_invoices` or `recurring_invoices` - a token allowed to write a
 * document must be able to read the vocabulary its documents are checked
 * against.
 *
 * @method Unit get(string $id, array $options = [])
 * @method \BeckonBilling\ApiClient\Collection<Unit> list(array $query = [], array $options = [])
 * @method \Generator<int,Unit> autoPaging(array $query = [], array $options = [])
 */
final class Units extends ReadOnlyResource
{
    protected function path(): string
    {
        return 'units';
    }

    protected function modelClass(): string
    {
        return Unit::class;
    }
}
