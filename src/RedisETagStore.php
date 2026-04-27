<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Redis;

/**
 * A Redis-backed ETag store for distributed deployments.
 *
 * Uses `SETEX` to store ETag strings with a TTL, `GET` to retrieve them, and
 * `SCAN` with a prefix pattern to bulk-delete ETags invalidated by mutations.
 * Suitable for multiserver deployments where all workers share the same Redis instance.
 *
 * Requires the `ext-redis` PHP extension and an injected `Redis` connection.
 *
 * <code>
 * Application::shared()->etagStore = new RedisETagStore();
 * </code>
 *
 * @see ETagStore
 * @see Application::$etagStore
 */
final readonly class RedisETagStore implements ETagStore
{
    private Redis $redis;

    public function __construct(string $host = "127.0.0.1", int $port = 6379)
    {
        $this->redis = new Redis();
        $this->redis->pconnect($host, $port) ?: throw new \RuntimeException("RedisETagStore: could not connect to Redis at $host:$port");
    }

    #[Override]
    public function get(string $key): ?string
    {
        $value = $this->redis->get($key);
        return is_string($value) ? $value : null;
    }

    #[Override]
    public function set(string $key, string $etag, int $ttl): void
    {
        $this->redis->setex($key, $ttl, $etag);
    }

    #[Override]
    public function deleteWithPrefix(string $prefix): void
    {
        $cursor = null;
        do {
            $keys = $this->redis->scan($cursor, $prefix . "*", 100);
            if ($keys) {
                $this->redis->del($keys);
            }
        } while ($cursor !== 0 && $cursor !== null);
    }
}
