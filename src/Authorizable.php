<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;

/**
 * Represents an entity that is both authenticatable and can have authorization capabilities.
 */
interface Authorizable extends Authenticatable
{
    /** @var Set<AuthorizableRole> Provides access to a sequence of {@see AuthorizableRole} objects associated with the authorizable entity. */
    public Set $roles {
        get;
    }

    public static function defaultSerialization(): Dictionary;
}
