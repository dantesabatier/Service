<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * Contract for the backend storage used by the idempotency layer.
 *
 * Implementations store and retrieve serialized response snapshots keyed by a scoped
 * idempotency key (client-supplied key + HTTP method + request path). The framework ships
 * with four built-in implementations:
 *
 * - `APCuIdempotencyStore` — shared across PHP workers on the same server via APCu shared
 *   memory. This is the default and requires the APCu extension.
 * - `RedisIdempotencyStore` — suitable for multiserver deployments.
 * - `MemcachedIdempotencyStore` — suitable for multiserver deployments.
 * - `InMemoryIdempotencyStore` — per-process, in-memory. Useful for testing.
 *
 * @see IdempotencyPolicy
 * @see Application::$idempotencyStore
 */
interface IdempotencyStore
{
    /**
     * Returns the stored response snapshot for the given key, or null if not found or expired.
     *
     * @param string $key The scoped idempotency key.
     */
    public function get(string $key): ?IdempotentResponse;

    /**
     * Stores a response snapshot under the given key for the specified duration.
     *
     * @param string $key The scoped idempotency key.
     * @param IdempotentResponse $response The response snapshot to store.
     * @param int $ttl Time-to-live in seconds.
     */
    public function store(string $key, IdempotentResponse $response, int $ttl): void;
}
