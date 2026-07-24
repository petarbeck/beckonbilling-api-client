<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Resource;

use BeckonBilling\ApiClient\Collection;
use BeckonBilling\ApiClient\Http\Transport;
use BeckonBilling\ApiClient\Model\Entity;

/**
 * Shared CRUD + pagination for the entity resources. Each subclass declares
 * its URL path and the {@see Entity} subclass it hydrates.
 *
 * Every method accepts an `$options` array forwarded to the transport; the
 * common key is `organisation` (a per-call organisation UUID override, or
 * `null` to send no organisation param for this call).
 */
abstract class AbstractResource
{
    public function __construct(protected readonly Transport $transport)
    {
    }

    /** URL path segment for this resource, e.g. "customers". */
    abstract protected function path(): string;

    /** Fully-qualified {@see Entity} subclass this resource hydrates. */
    abstract protected function modelClass(): string;

    /**
     * List resources (one page).
     *
     * @param array<string,mixed> $query   e.g. ['limit' => 50, 'offset' => 0, ...filters]
     * @param array<string,mixed> $options
     * @return Collection<Entity>
     */
    public function list(array $query = [], array $options = []): Collection
    {
        $options['query'] = $query;
        $response = $this->transport->request('GET', $this->path(), $options);

        return $this->toCollection($response);
    }

    /**
     * Iterate every resource across all pages, fetching lazily.
     *
     * @param array<string,mixed> $query
     * @param array<string,mixed> $options
     * @return \Generator<int,Entity>
     */
    public function autoPaging(array $query = [], array $options = []): \Generator
    {
        $limit = isset($query['limit']) ? max(1, (int) $query['limit']) : 100;
        $offset = isset($query['offset']) ? max(0, (int) $query['offset']) : 0;

        do {
            $page = $this->list(['limit' => $limit, 'offset' => $offset] + $query, $options);
            foreach ($page as $item) {
                yield $item;
            }
            $offset = $page->nextOffset();
        } while ($offset !== null && count($page) > 0);
    }

    /**
     * @param array<string,mixed> $options
     */
    public function get(string $id, array $options = []): Entity
    {
        $response = $this->transport->request('GET', $this->itemPath($id), $options);

        return $this->hydrate($response);
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $options
     */
    public function create(array $data, array $options = []): Entity
    {
        $options['json'] = $data;
        $response = $this->transport->request('POST', $this->path(), $options);

        return $this->hydrate($response);
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $options
     */
    public function update(string $id, array $data, array $options = []): Entity
    {
        $options['json'] = $data;
        $response = $this->transport->request('PUT', $this->itemPath($id), $options);

        return $this->hydrate($response);
    }

    /**
     * @param array<string,mixed> $options
     */
    public function delete(string $id, array $options = []): void
    {
        $this->transport->request('DELETE', $this->itemPath($id), $options);
    }

    protected function itemPath(string $id): string
    {
        return $this->path() . '/' . rawurlencode($id);
    }

    /**
     * @param array<string,mixed> $data
     */
    protected function hydrate(array $data): Entity
    {
        $class = $this->modelClass();

        return new $class($data);
    }

    /**
     * @param array<string,mixed> $response
     * @return Collection<Entity>
     */
    protected function toCollection(array $response): Collection
    {
        $rows = is_array($response['data'] ?? null) ? $response['data'] : [];
        $data = [];
        foreach ($rows as $row) {
            $data[] = $this->hydrate(is_array($row) ? $row : []);
        }

        return new Collection(
            $data,
            (int) ($response['total'] ?? count($data)),
            (int) ($response['limit'] ?? count($data)),
            (int) ($response['offset'] ?? 0),
        );
    }
}
