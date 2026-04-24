<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Set;

/**
 * Contract for a named role that aggregates a set of fine-grained authorizations.
 *
 * Roles are owned by `Authorizable` entities. When `AuthorizationService` evaluates
 * whether a user may perform an action, it traverses the user's roles and their
 * associated `Authorization` objects to resolve the effective permission set.
 *
 * Implement this interface on the managed object class that represents a role in the
 * application's domain model.
 *
 * @see Authorizable
 * @see Authorization
 * @see AuthorizationService
 */
interface AuthorizableRole
{
    /** @var string The name of the role */
    public string $name {
        get;
    }
    /** @var Set<Authorization> Provides access to a sequence of {@see Authorization} objects associated with the role. */
    public Set $authorizations {
        get;
    }
}
