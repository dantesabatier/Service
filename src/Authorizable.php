<?php

namespace Sabatier\Service;

/**
 * Represents an entity that is both authenticatable and can have authorization capabilities.
 */
interface Authorizable extends Authenticatable
{
    /** @var Authorization|null Provides access to an {@see Authorization} object, defining the authorization capabilities and access levels of the entity. */
    public ?Authorization $authorization {
        get;
        set;
    }
}
