<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Redis;

/**
 * A Redis-backed rate limit store for distributed deployments.
 *
 * Uses `INCR` to atomically increment the counter, followed by `EXPIRE` when the key is
 * first created (count === 1). This is the standard Redis rate-limiting pattern: the first
 * request creates the key with a TTL, and subsequent requests only bump the counter without
 * resetting the TTL.
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
        $this->redis->pconnect($host, $port);
    }

    #[Override]
    public function increment(string $key, int $windowSeconds): int
    {
        $count = $this->redis->incr($key);
        if ($count === 1) {
            $this->redis->expire($key, $windowSeconds);
        }
        return is_int($count) ? $count : 0;
    }

    #[Override]
    public function ttl(string $key): int
    {
        $ttl = $this->redis->ttl($key);
        return is_int($ttl) ? max(0, $ttl) : 0;
    }
}
