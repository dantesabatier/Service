<?php

namespace Sabatier\Service;

/**
 * Represents an entity that is both authenticatable and can have authorization capabilities.
 */
interface Authorizable extends Authenticatable
{
    public ?Authorization $authorization {
        get;
    }
}
