<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Sequence;

/**
 * Represents an entity that is both authenticatable and can have authorization capabilities.
 */
interface Authorizable extends Authenticatable
{
    /** @var Sequence<AuthorizableRole> Provides access to a sequence of {@see AuthorizableRole} objects associated with the authorizable entity. */
    public Sequence $roles {
        get;
    }

    public static function defaultSerialization(): Dictionary;
}
