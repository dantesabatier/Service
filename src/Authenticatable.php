<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Equatable;

/**
 * Contract for entities that can be authenticated by the framework.
 *
 * `Authenticatable` is the minimal identity contract: a username, an optional
 * password, and an enabled flag. It is the base of `Authorizable`, which adds
 * role membership for authorization decisions.
 *
 * Authentication providers (Basic, Bearer, Digest) receive an `Authenticatable`
 * instance resolved by `AuthenticationService` and validate the supplied credentials
 * against it.
 *
 * Implement this interface — or more commonly `Authorizable` — on the managed object
 * class that represents a user in the application's domain model.
 *
 * @see Authorizable
 * @see AuthenticationService
 */
interface Authenticatable extends Equatable
{
    /** @var string The username of the authenticatable entity. */
    public string $username {
        get;
    }
    /** @var string|null The password of the authenticatable entity. */
    public ?string $password {
        get;
    }
    /** @var bool Indicates whether the authenticatable entity is enabled and can authenticate. */
    public bool $isEnabled {
        get;
    }
}
