<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Memcached;
use Override;
use Sabatier\Foundation\ArrayClass;

/**
 * A Memcached-backed authorization cache for distributed deployments.
 *
 * Stores serialized `ArrayClass<Authorization>` sets in Memcached, keyed by the
 * authorizable entity's username. Uses `Memcached::set()` to store with a TTL and
 * `Memcached::get()` to retrieve. Suitable for multiserver deployments where all
 * workers share the same Memcached instance.
 *
 * Requires the `ext-memcached` PHP extension and an injected `Memcached` connection.
 *
 * <code>
 * Application::shared()->authorizationPersistentCache = new MemcachedAuthorizationCache();
 * </code>
 *
 * @see AuthorizationCache
 * @see Application::$authorizationPersistentCache
 */
final readonly class MemcachedAuthorizationCache implements AuthorizationCache
{
    private Memcached $memcached;

    public function __construct(string $host = "127.0.0.1", int $port = 11211, private int $ttl = 3600)
    {
        $this->memcached = new Memcached();
        $this->memcached->addServer($host, $port);
    }

    #[Override]
    public function getAuthorizableAuthorizations(Authorizable $authorizable): ?ArrayClass
    {
        $value = $this->memcached->get("auth:u:$authorizable->username");
        if ($value === false) {
            return null;
        }
        /** @var ArrayClass<Authorization> $result */
        $result = unserialize((string)$value);
        return $result;
    }

    #[Override]
    public function setAuthorizableAuthorizations(Authorizable $authorizable, ArrayClass $authorizations): void
    {
        $this->memcached->set("auth:u:$authorizable->username", serialize($authorizations), $this->ttl);
    }

    #[Override]
    public function invalidateAuthorizable(Authorizable $authorizable): void
    {
        $this->memcached->delete("auth:u:$authorizable->username");
    }
}
