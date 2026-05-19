<?php

declare(strict_types=1);

namespace Sabatier\Service;

use APCUIterator;
use Override;
use Sabatier\Foundation\ArrayClass;

/**
 * An APCu-backed authorization cache shared across PHP workers on the same server.
 *
 * Stores serialized `ArrayClass<Authorization>` sets in APCu shared memory, keyed by
 * the authorizable entity's username. This reduces database round-trips for repeat
 * requests by the same user across different PHP requests on the same machine.
 *
 * Requires the APCu PHP extension (`ext-apcu`). For multiserver deployments, replace
 * with a distributed store (e.g., Redis or Memcached).
 *
 * <code>
 * Application::shared()->authorizationPersistentCache = new APCuAuthorizationCache();
 * </code>
 *
 * @see AuthorizationCache
 * @see Application::$authorizationPersistentCache
 */
final readonly class APCuAuthorizationCache implements AuthorizationCache
{
    public function __construct(private int $ttl = 3600)
    {
    }

    #[Override]
    public function getAuthorizableAuthorizations(Authorizable $authorizable): ?ArrayClass
    {
        $value = apcu_fetch("auth:u:$authorizable->username", $success);
        if (!$success) {
            return null;
        }
        /** @var ArrayClass<Authorization> $result */
        $result = unserialize((string)$value);
        return $result;
    }

    #[Override]
    public function setAuthorizableAuthorizations(Authorizable $authorizable, ArrayClass $authorizations): void
    {
        apcu_store("auth:u:$authorizable->username", serialize($authorizations), $this->ttl);
    }

    #[Override]
    public function invalidateAuthorizable(Authorizable $authorizable): void
    {
        apcu_delete("auth:u:$authorizable->username");
    }

    #[Override]
    public function invalidateAll(): void
    {
        apcu_delete(new APCUIterator("/^auth:u:/"));
    }
}
