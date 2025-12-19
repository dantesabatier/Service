<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Set;

/**
 * Represents a role that can be authorized within the system.
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
