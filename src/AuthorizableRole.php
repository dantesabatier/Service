<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Sequence;

/**
 * Represents a role that can be authorized within the system.
 */
interface AuthorizableRole
{
    /** @var string|null The name of the role */
    public ?string $name {
        get;
    }
    /** @var Sequence<Authorization> Provides access to a sequence of {@see Authorization} objects associated with the role. */
    public Sequence $authorizations {
        get;
    }
}
