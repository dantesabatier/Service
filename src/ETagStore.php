<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * Contract for the backend storage used by the server-side ETag cache.
 *
 * Implementations store and retrieve ETag strings keyed by a scoped cache key
 * (HTTP method + request path + query + serialization). Before executing an action,
 * `Responder` queries the store to short-circuit the request with a 304 Not Modified
 * response when the client's `If-None-Match` header matches the stored ETag, avoiding
 * unnecessary DB and cache queries. After a successful GET, the resulting ETag is persisted.
 * Mutations (POST, PATCH, DELETE) invalidate all ETag entries for the affected path.
 *
 * The framework ships with three built-in implementations:
 *
 * - `APCuETagStore` — shared across PHP workers on the same server via APCu shared
 *   memory. This is the default and requires the APCu extension.
 * - `RedisETagStore` — suitable for multiserver deployments.
 * - `InMemoryETagStore` — per-process, in-memory. Useful for testing.
 *
 * @see HTTPCachePolicy
 * @see Application::$etagStore
 */
interface ETagStore
{
    /**
     * Returns the stored ETag for the given cache key, or null if not found or expired.
     *
     * @param string $key The scoped ETag cache key.
     */
    public function get(string $key): ?string;

    /**
     * Stores an ETag string under the given key for the specified duration.
     *
     * @param string $key The scoped ETag cache key.
     * @param string $etag The ETag value to store (including surrounding quotes).
     * @param int $ttl Time-to-live in seconds.
     */
    public function set(string $key, string $etag, int $ttl): void;

    /**
     * Deletes all stored ETags whose key starts with the given prefix.
     * Used to invalidate GET ETags after a mutation on the same path.
     *
     * @param string $prefix The key prefix to match and delete.
     */
    public function deleteWithPrefix(string $prefix): void;
}
