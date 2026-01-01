<?php

namespace Sabatier\Service;

/**
 * Defines a contract for any class that can handle authentication processes.
 */
interface Authenticatable
{
    /** @var string The username of the authenticatable entity. */
    public string $username {
        get;
    }
    /** @var string|null The password of the authenticatable entity. */
    public ?string $password {
        get;
    }
}
