<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Resource;

use BeckonBilling\ApiClient\Model\Unit;

/**
 * Units of measure - `/api/v1/units`. **Read-only.**
 *
 * A FIXED list of 31 system units, the same for every organisation. It is not
 * a vocabulary any more: nothing you send adds to it, and nothing is renamed
 * out of it.
 *
 * Read it for the `key` values. An article's `unit` and a position's
 * `unit_key` must both be one of them; a printed short form is not a key.
 *
 * **No permission is required** - a fixed list that is identical everywhere
 * discloses nothing about the organisation, so any valid token may read it.
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
