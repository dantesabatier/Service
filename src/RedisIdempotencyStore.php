<?php

namespace Sabatier\Service;

use Override;
use Redis;

/**
 * A Redis-backed idempotency store for distributed deployments.
 *
 * Uses `SETEX` to store serialized response snapshots with a TTL, and `GET` to retrieve them.
 * Suitable for multiserver deployments where all workers share the same Redis instance.
 *
 * Requires the `ext-redis` PHP extension and an injected `Redis` connection.
 *
 * <code>
 * $redis = new Redis();
 * $redis->connect('127.0.0.1', 6379);
 * Application::shared()->idempotencyStore = new RedisIdempotencyStore($redis);
 * </code>
 *
 * @see IdempotencyStore
 * @see Application::$idempotencyStore
 */
final readonly class RedisIdempotencyStore implements IdempotencyStore
{
    public function __construct(private Redis $redis)
    {
    }

    #[Override]
    public function get(string $key): ?IdempotentResponse
    {
        $value = $this->redis->get($key);
        if (!is_string($value)) {
            return null;
        }
        /** @var IdempotentResponse $result */
        $result = unserialize($value);
        return $result;
    }

    #[Override]
    public function store(string $key, IdempotentResponse $response, int $ttl): void
    {
        $this->redis->setex($key, $ttl, serialize($response));
    }
}
