<?php

namespace Sabatier\Service;

use Override;
use Redis;

/**
 * A Redis-backed rate limit store for distributed deployments.
 *
 * Uses two atomic Redis operations in sequence: `SET NX EX` initializes the key with a TTL
 * only when it does not exist, and `INCR` atomically increments the counter on every request.
 * This combination is race-safe: concurrent workers that both issue `SET NX` will have only
 * one succeed; both will then `INCR` the same key, producing correct sequential counts.
 *
 * Requires the `ext-redis` PHP extension and an injected `Redis` connection.
 * Suitable for multiserver deployments where all workers share the same Redis instance.
 *
 * <code>
 * Application::shared()->rateLimitStore = new RedisRateLimitStore();
 * </code>
 *
 * @see RateLimitStore
 * @see Application::$rateLimitStore
 */
final readonly class RedisRateLimitStore implements RateLimitStore
{
    private Redis $redis;

    public function __construct(string $host = "127.0.0.1", int $port = 6379)
    {
        $this->redis = new Redis();
        $this->redis->pconnect($host, $port) ?: throw new \RuntimeException("RedisRateLimitStore: could not connect to Redis at $host:$port");
    }

    #[Override]
    public function increment(string $key, int $windowSeconds): int
    {
        $this->redis->set($key, "0", ["nx", "ex" => $windowSeconds]);
        $count = $this->redis->incr($key);
        return is_int($count) ? $count : 0;
    }

    #[Override]
    public function ttl(string $key): int
    {
        $ttl = $this->redis->ttl($key);
        return is_int($ttl) ? max(0, $ttl) : 0;
    }
}
