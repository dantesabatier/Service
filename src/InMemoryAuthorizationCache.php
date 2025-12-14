<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\ObjectClass;

class InMemoryAuthorizationCache extends ObjectClass implements AuthorizationCache
{
    #[Override]
    public function getAuthorizableAuthorizations(Authorizable $authorizable): ?ArrayClass
    {
        return static::staticAssociatedValueForKey("u:$authorizable->username") ?? null;
    }

    #[Override]
    public function setAuthorizableAuthorizations(Authorizable $authorizable, ArrayClass $authorizations): void
    {
        static::setStaticAssociatedValueForKey($authorizations, "u:$authorizable->username");
    }

    #[Override]
    public function invalidateAuthorizable(Authorizable $authorizable): void
    {
        static::setStaticAssociatedValueForKey(null, "u:$authorizable->username");
    }
}
