<?php

namespace Sabatier\Service;

/**
 * Defines a contract for any class that can handle authentication processes.
 */
interface Authenticatable
{
    public ?string $username {
        get;
    }
    public ?string $password {
        get;
    }
}
