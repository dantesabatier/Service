<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\Set;

/** @internal */
final readonly class FieldRule
{
    public bool $requiresOwner;

    /**
     * @param Set<string> $roles
     * @param AuthorizationScope $scope
     */
    public function __construct(public Set $roles, public AuthorizationScope $scope)
    {
        $this->requiresOwner = $this->scope === AuthorizationScope::own;
    }

    /**
     * @param Set<string> $userRoles
     */
    public function allowsRoles(Set $userRoles): bool
    {
        return !$this->roles->isDisjoint($userRoles);
    }
}
