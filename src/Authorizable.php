<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Representable;
use Sabatier\Foundation\Set;

/**
 * Contract for user entities that support both authentication and role-based authorization.
 *
 * Implement this interface on the Core Data managed object class that represents a user.
 * `AuthenticationService` discovers the implementing entity automatically at runtime by
 * scanning the managed object model, so no explicit registration is required.
 *
 * `Authorizable` extends `Authenticatable` (username/password/enabled) with role membership
 * and a refresh token version counter used to invalidate outstanding tokens when the user's
 * credentials change.
 *
 * @see Authenticatable
 * @see AuthorizableRole
 * @see AuthenticationService
 * @see AuthorizationService
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
