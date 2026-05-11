<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * A lightweight value object representing a cached authorization entry.
 *
 * Used exclusively by persistent `AuthorizationCache` implementations (APCu, Redis, Memcached)
 * as the serialized form. Stores only the three scalars needed to evaluate authorization,
 * decoupling the cache from the `ManagedObject` that originally provided the data.
 *
 * @see AuthorizationCache
 * @see Authorization
 */
final readonly class CachedAuthorization implements Authorization
{
    public function __construct(public string $name, public AuthorizationType $type, public AuthorizationScope $scope)
    {
    }
}
