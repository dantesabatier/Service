<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Equatable;

/**
 * Defines a contract for any class that can handle authentication processes.
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
