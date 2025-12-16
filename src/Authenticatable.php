<?php

namespace Sabatier\Service;

/**
 * Defines a contract for any class that can handle authentication processes.
 */
interface Authenticatable
{
    /** @var string The username associated with the authentication process. */
    public string $username {
        get;
    }
    /** @var string|null The password associated with the authentication process. */
    public ?string $password {
        get;
    }
}
