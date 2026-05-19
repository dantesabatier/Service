<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\ObjectClass;

final class InMemoryAuthorizationCache extends ObjectClass implements AuthorizationCache
{
    #[Override]
    public function getAuthorizableAuthorizations(Authorizable $authorizable): ?ArrayClass
    {
        return InMemoryAuthorizationCache::staticAssociatedValueForKey("u:$authorizable->username") ?? null;
    }

    #[Override]
    public function setAuthorizableAuthorizations(Authorizable $authorizable, ArrayClass $authorizations): void
    {
        InMemoryAuthorizationCache::setStaticAssociatedValueForKey($authorizations, "u:$authorizable->username");
    }

    #[Override]
    public function invalidateAuthorizable(Authorizable $authorizable): void
    {
        InMemoryAuthorizationCache::setStaticAssociatedValueForKey(null, "u:$authorizable->username");
    }

    #[Override]
    public function invalidateAll(): void
    {
        InMemoryAuthorizationCache::$staticAssociatedValues[InMemoryAuthorizationCache::class] = [];
    }
}
