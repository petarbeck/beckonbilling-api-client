<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Resource;

use BeckonBilling\ApiClient\Model\Entity;

/**
 * A collection this API only lets you READ: `list()`, `autoPaging()`, `get()`.
 *
 * The server answers 405 to every other method, so the three writers are
 * closed here rather than sent and refused - a `\LogicException` names the
 * resource and costs no round trip, and it makes "read-only" a property of the
 * client rather than a sentence in a docblock.
 */
abstract class ReadOnlyResource extends AbstractResource
{
    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $options
     */
    public function create(array $data, array $options = []): Entity
    {
        throw $this->readOnly('create');
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $options
     */
    public function update(string $id, array $data, array $options = []): Entity
    {
        throw $this->readOnly('update');
    }

    /**
     * @param array<string,mixed> $options
     */
    public function delete(string $id, array $options = []): void
    {
        throw $this->readOnly('delete');
    }

    private function readOnly(string $action): \LogicException
    {
        return new \LogicException(sprintf(
            'Cannot %s: /%s is read-only on the API (v1). Manage it in the portal.',
            $action,
            $this->path()
        ));
    }
}
