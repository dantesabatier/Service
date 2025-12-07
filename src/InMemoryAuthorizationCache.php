<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\ObjectClass;

class InMemoryAuthorizationCache extends ObjectClass implements AuthorizationCache
{
    /**
     * @inheritDoc
     */
    public function getAuthorizableAuthorizations(Authorizable $authorizable): ?ArrayClass
    {
        return static::staticAssociatedValueForKey("u:$authorizable->username") ?? null;
    }

    /**
     * @inheritDoc
     */
    public function setAuthorizableAuthorizations(Authorizable $authorizable, ArrayClass $authorizations): void
    {
        static::setStaticAssociatedValueForKey($authorizations, "u:$authorizable->username");
    }

    /**
     * @inheritDoc
     */
    public function invalidateAuthorizable(Authorizable $authorizable): void
    {
        static::setStaticAssociatedValueForKey(null, "u:$authorizable->username");
    }
}
