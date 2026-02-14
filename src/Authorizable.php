<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Representable;
use Sabatier\Foundation\Set;

/**
 * Defines a contract for any class that can handle authentication and authorization processes.
 */
interface Authorizable extends Authenticatable, Representable
{
    /** @var int<0, max> Indicates the current refresh version of the entity. This property is used to validate refresh tokens. When a token is presented, its version is compared against this value. If the token version is lower, the token is considered invalid. */
    public int $refreshTokenVersion {
        get;
        set;
    }
    /** @var Set<AuthorizableRole> Provides access to a sequence of {@see AuthorizableRole} objects associated with the authorizable entity. */
    public Set $roles {
        get;
    }
}
