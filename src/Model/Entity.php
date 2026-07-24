<?php

declare(strict_types=1);

namespace BeckonBilling\ApiClient\Model;

/**
 * Immutable value object wrapping one API resource's JSON payload.
 *
 * Documented fields are reachable as properties (`$customer->label`) or array
 * keys (`$customer['label']`); the full payload is always available via
 * {@see self::toArray()}. Because the wrapper never declares a fixed field
 * list, new fields the API adds are reachable immediately - a consumer never
 * has to wait for a client release to read them.
 */
abstract class Entity implements \ArrayAccess, \JsonSerializable
{
    /**
     * @param array<string,mixed> $attributes
     */
    public function __construct(public readonly array $attributes)
    {
    }

    /** The resource's opaque UUID (`id`), or null if absent. */
    public function id(): ?string
    {
        $id = $this->attributes['id'] ?? null;

        return $id === null ? null : (string) $id;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('API resources are immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('API resources are immutable.');
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->attributes;
    }
}
