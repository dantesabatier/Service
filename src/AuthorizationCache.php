<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;

/**
 * Contract for caching resolved authorization sets for `Authorizable` entities.
 *
 * `AuthorizationService` uses two cache layers that both implement this interface:
 * - **In-request cache** (`InMemoryAuthorizationCache` by default) — lives for the duration
 *   of a single PHP request. Always present.
 * - **Persistent cache** (optional, e.g. Redis or APCu) — survives across requests, reducing
 *   database round-trips for repeat requests by the same user.
 *
 * Override `Application::$authorizationCache` in the application delegate to supply a
 * persistent implementation:
 *
 * <code>
 * Application::shared()->authorizationCache = new RedisAuthorizationCache($redis);
 * </code>
 *
 * Implementations must be safe to call in any order: `get` returns `null` on a cache miss,
 * `set` stores the resolved set, and `invalidate` removes it when the user's roles change.
 *
 * @see AuthorizationService
 * @see Application::$authorizationCache
 */
interface AuthorizationCache
{
    /**
     * Retrieves the cached authorizations for a given authorizable entity.
     *
     * @param Authorizable $authorizable The authorizable entity whose authorizations are to be retrieved.
     * @return ArrayClass<Authorization>|null An ArrayClass containing the authorizations, or null if not cached.
     */
    public function getAuthorizableAuthorizations(Authorizable $authorizable): ?ArrayClass;

    /**
     * Caches the authorizations for a given authorizable entity.
     *
     * @param Authorizable $authorizable The authorizable entity whose authorizations are to be cached.
     * @param ArrayClass<Authorization> $authorizations An ArrayClass containing the authorizations to cache.
     */
    public function setAuthorizableAuthorizations(Authorizable $authorizable, ArrayClass $authorizations): void;

    /**
     * Invalidates the cached authorizations for a given authorizable entity.
     *
     * @param Authorizable $authorizable The authorizable entity whose cached authorizations are to be invalidated.
     */
    public function invalidateAuthorizable(Authorizable $authorizable): void;
}
