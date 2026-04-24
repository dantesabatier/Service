<?php

namespace Sabatier\Service;

use Memcached;
use Override;

/**
 * A Memcached-backed rate limit store for distributed deployments.
 *
 * Uses `Memcached::add()` to atomically initialize the counter only when the key does not exist,
 * mirroring the `apcu_add` pattern in APCuRateLimitStore. Subsequent requests use
 * `Memcached::increment()` to atomically advance the counter.
 *
 * Because Memcached does not expose the remaining TTL of a key, this store maintains a parallel
 * key (`$key:reset`) holding the Unix timestamp at which the window expires. The `ttl()` method
 * derives the remaining seconds from that timestamp.
 *
 * Requires the `ext-memcached` PHP extension and an injected `Memcached` connection.
 * Suitable for multi-server deployments where all workers share the same Memcached instance.
 *
 * <code>
 * $memcached = new Memcached();
 * $memcached->addServer('127.0.0.1', 11211);
 * Application::shared()->rateLimitStore = new MemcachedRateLimitStore($memcached);
 * </code>
 *
 * @see RateLimitStore
 * @see Application::$rateLimitStore
 */
final class MemcachedRateLimitStore implements RateLimitStore
{
    public function __construct(private Memcached $memcached)
    {
    }

    #[Override]
    public function increment(string $key, int $windowSeconds): int
    {
        if ($this->memcached->add($key, 1, $windowSeconds)) {
            $this->memcached->add("$key:reset", time() + $windowSeconds, $windowSeconds);
            return 1;
        }
        $count = $this->memcached->increment($key);
        return $count !== false ? (int)$count : 1;
    }

    #[Override]
    public function ttl(string $key): int
    {
        $reset = $this->memcached->get("$key:reset");
        if ($reset === false) {
            return 0;
        }
        return max(0, (int)$reset - time());
    }
}
