<?php

namespace Sabatier\Service;

/**
 * Represents an authorization entity.
 */
interface Authorization
{
    /** @var string|null The name of the authorization */
    public ?string $name {
        get;
    }
    /** @var AuthorizationType The type of the authorization */
    public AuthorizationType $type {
        get;
    }
}
