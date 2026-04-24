<?php

namespace Sabatier\Service;

/**
 * Contract for the backend storage used by the rate limiter.
 *
 * Implementations must atomically increment a named counter and report how many seconds
 * remain in its current window. The framework ships with two built-in implementations:
 *
 * - `APCuRateLimitStore` — shared across PHP workers on the same server via APCu shared
 *   memory. This is the default and requires the APCu extension.
 * - `InMemoryRateLimitStore` — per-process, in-memory. Useful for testing or environments
 *   where APCu is unavailable. Not suitable for production with multiple workers.
 *
 * For distributed deployments (multiple servers), supply a `RedisRateLimitStore` or similar
 * implementation via `Application::$rateLimitStore` in the application delegate.
 *
 * @see RateLimitPolicy
 * @see Application::$rateLimitStore
 */
interface RateLimitStore
{
    /**
     * Atomically increments the request counter for the given key.
     *
     * If the key does not exist, it is created with a value of 1 and a TTL of `$windowSeconds`.
     * If the key already exists, the counter is incremented and the existing TTL is preserved.
     *
     * @param string $key           The rate limit bucket identifier (e.g. `"rate_limit:192.168.1.1"`).
     * @param int    $windowSeconds The window duration in seconds, applied only when creating the key.
     * @return int The new counter value after incrementing.
     */
    public function increment(string $key, int $windowSeconds): int;

    /**
     * Returns the number of seconds remaining until the current window expires.
     *
     * @param string $key The rate limit bucket identifier.
     * @return int Seconds until reset, or 0 if the key does not exist or has already expired.
     */
    public function ttl(string $key): int;
}
