<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;

/**
 * Defines a contract for any class that can handle authentication and authorization processes.
 */
interface Authorizable extends Authenticatable
{
    /** @var Set<AuthorizableRole> Provides access to a sequence of {@see AuthorizableRole} objects associated with the authorizable entity. */
    public Set $roles {
        get;
    }
    /** @var int<0, max> Indicates the current refresh version of the entity. This property is used to validate refresh tokens. When a token is presented, its version is compared against this value. If the token version is lower, the token is considered invalid. */
    public int $version {
        get;
        set;
    }

    /**
     * Returns a dictionary defining the default representation of the object.
     *
     * This serves as a fallback when a {@see Request} does not provide a specific serialization configuration. It ensures that the most essential fields (e.g., username, roles, version) are included so that the request can be processed safely.
     *
     * Developers may provide a more detailed representation in the request, in which case this fallback is not used.
     *
     * @return Dictionary<mixed> A dictionary describing the default fields and nested structure to include in the serialized output.
     */
    public static function defaultRepresentation(): Dictionary;
}
