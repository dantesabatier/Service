<?php

namespace Sabatier\Service;

/**
 * Represents an authorization entity.
 */
interface Authorization
{
    public ?string $name {
        get;
    }
    public AuthorizationType $type {
        get;
    }
}
