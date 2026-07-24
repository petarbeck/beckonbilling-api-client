<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient;

use BeckonBilling\ApiClient\Model\Entity;

/**
 * One page of a paginated list response (`{ data, total, limit, offset }`).
 *
 * Iterable and countable over the hydrated {@see Entity} objects on this page;
 * `total` is the full server-side count across all pages.
 *
 * @template T of Entity
 * @implements \IteratorAggregate<int,T>
 */
final class Collection implements \IteratorAggregate, \Countable, \JsonSerializable
{
    /**
     * @param list<T> $data
     */
    public function __construct(
        public readonly array $data,
        public readonly int $total,
        public readonly int $limit,
        public readonly int $offset,
    ) {
    }

    /** True when more results exist beyond this page. */
    public function hasMore(): bool
    {
        return ($this->offset + count($this->data)) < $this->total;
    }

    /** The offset to pass for the next page, or null when there is none. */
    public function nextOffset(): ?int
    {
        return $this->hasMore() ? $this->offset + count($this->data) : null;
    }

    /**
     * @return \ArrayIterator<int,T>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->data);
    }

    public function count(): int
    {
        return count($this->data);
    }

    /**
     * @return array{data: list<T>, total: int, limit: int, offset: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'data' => $this->data,
            'total' => $this->total,
            'limit' => $this->limit,
            'offset' => $this->offset,
        ];
    }
}
