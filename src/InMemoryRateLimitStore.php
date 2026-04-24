<?php

namespace Sabatier\Service;

/**
 * A per-process, in-memory rate limit store intended for testing and development.
 *
 * Counters are stored in a static array and are shared within a single PHP process
 * but not across workers or requests in standard FPM deployments. This makes it
 * unsuitable for production use with multiple workers.
 *
 * Use this store when APCu is unavailable or in unit tests where isolation is needed.
 *
 * @see RateLimitStore
 * @see APCuRateLimitStore
 */
final class InMemoryRateLimitStore implements RateLimitStore
{
    /** @var array<string, array{count: int, expiresAt: int}> */
    private static array $buckets = [];

    public function increment(string $key, int $windowSeconds): int
    {
        $now = time();
        if (!isset(self::$buckets[$key]) || self::$buckets[$key]["expiresAt"] <= $now) {
            self::$buckets[$key] = ["count" => 1, "expiresAt" => $now + $windowSeconds];
            return 1;
        }
        return ++self::$buckets[$key]["count"];
    }

    public function ttl(string $key): int
    {
        $now = time();
        if (!isset(self::$buckets[$key]) || self::$buckets[$key]["expiresAt"] <= $now) {
            return 0;
        }
        return self::$buckets[$key]["expiresAt"] - $now;
    }
}
