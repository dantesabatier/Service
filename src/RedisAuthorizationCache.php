<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Redis;
use Sabatier\Foundation\ArrayClass;

/**
 * A Redis-backed authorization cache for distributed deployments.
 *
 * Stores serialized `ArrayClass<Authorization>` sets in Redis, keyed by the authorizable
 * entity's username. Uses `SETEX` to store with a TTL and `GET` to retrieve. Suitable for
 * multiserver deployments where all workers share the same Redis instance.
 *
 * Requires the `ext-redis` PHP extension and an injected `Redis` connection.
 *
 * <code>
 * Application::shared()->authorizationPersistentCache = new RedisAuthorizationCache();
 * </code>
 *
 * @see AuthorizationCache
 * @see Application::$authorizationPersistentCache
 */
final readonly class RedisAuthorizationCache implements AuthorizationCache
{
    private Redis $redis;

    public function __construct(string $host = "127.0.0.1", int $port = 6379, private int $ttl = 3600)
    {
        $this->redis = new Redis();
        $this->redis->pconnect($host, $port);
    }

    #[Override]
    public function getAuthorizableAuthorizations(Authorizable $authorizable): ?ArrayClass
    {
        $value = $this->redis->get("auth:u:$authorizable->username");
        if (!is_string($value)) {
            return null;
        }
        /** @var ArrayClass<Authorization> $result */
        $result = unserialize($value);
        return $result;
    }

    #[Override]
    public function setAuthorizableAuthorizations(Authorizable $authorizable, ArrayClass $authorizations): void
    {
        $this->redis->setex("auth:u:$authorizable->username", $this->ttl, serialize($authorizations));
    }

    #[Override]
    public function invalidateAuthorizable(Authorizable $authorizable): void
    {
        $this->redis->del("auth:u:$authorizable->username");
    }

    /**
     * The walk ends when the cursor returns to 0, not on the first empty batch: `SCAN` answers an empty batch whenever the slice it visited holds no matching key, and in a Redis shared with other caches that is most of them.
     */
    #[Override]
    public function invalidateAll(): void
    {
        $keys = [];
        $iterator = null;
        do {
            $keys = [...$keys, ...($this->redis->scan($iterator, "auth:u:*", 100) ?: [])];
        } while ($iterator > 0);
        if ($keys !== []) {
            $this->redis->del($keys);
        }
    }
}
