<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * Contract for a single, fine-grained permission entry attached to a role.
 *
 * An authorization combines three dimensions:
 * - `$name` — the resource it protects (e.g. `"posts"`, `"users"`).
 * - `$type` — the action allowed on that resource (e.g. `read`, `write`, `delete`, `any`).
 * - `$scope` — whether the permission covers all records (`AuthorizationScope::all`) or
 *   only records owned by the authenticated user (`AuthorizationScope::own`).
 *
 * `AuthorizationService::isAuthorized()` matches incoming requests against the
 * resolved set of authorizations for the current user.
 *
 * @see AuthorizationType
 * @see AuthorizationScope
 * @see AuthorizableRole
 * @see AuthorizationService
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
