<?php

namespace Sabatier\Service;

use Memcached;
use Override;

/**
 * A Memcached-backed idempotency store for distributed deployments.
 *
 * Uses `Memcached::set()` to store serialized response snapshots with a TTL, and
 * `Memcached::get()` to retrieve them. Suitable for multiserver deployments where all
 * workers share the same Memcached instance.
 *
 * Requires the `ext-memcached` PHP extension and an injected `Memcached` connection.
 *
 * <code>
 * Application::shared()->idempotencyStore = new MemcachedIdempotencyStore();
 * </code>
 *
 * @see IdempotencyStore
 * @see Application::$idempotencyStore
 */
final readonly class MemcachedIdempotencyStore implements IdempotencyStore
{
    private Memcached $memcached;

    public function __construct(string $host = "127.0.0.1", int $port = 11211)
    {
        $this->memcached = new Memcached();
        $this->memcached->addServer($host, $port);
    }

    #[Override]
    public function get(string $key): ?IdempotentResponse
    {
        $value = $this->memcached->get($key);
        if ($value === false) {
            return null;
        }
        /** @var IdempotentResponse $result */
        $result = unserialize((string)$value);
        return $result;
    }

    #[Override]
    public function store(string $key, IdempotentResponse $response, int $ttl): void
    {
        $this->memcached->set($key, serialize($response), $ttl);
    }
}
