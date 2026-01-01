<?php

namespace Sabatier\Service;

/**
 * Represents an authorization entity.
 */
interface Authorization
{
    /** @var string The name of the authorization */
    public string $name {
        get;
    }
    /** @var AuthorizationType The type of the authorization */
    public AuthorizationType $type {
        get;
    }
    /** @var AuthorizationScope Defines the scope of the authorization. This property determines whether the authorization applies to all resources of a given type or only to resources owned by the authenticated user. For example, {@see AuthorizationScope::all} grants access to all resources of this type, while {@see AuthorizationScope::own} grants access only to resources owned by the user. This is used when generating authorization scopes and during ownership checks to enforce proper access control. */
    public AuthorizationScope $scope {
        get;
    }
}
