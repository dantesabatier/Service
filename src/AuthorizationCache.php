<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;

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
