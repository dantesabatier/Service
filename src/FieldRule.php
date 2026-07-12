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
     * @param string|null $where Raw predicate format string gating the field, resolved against the resource with `$SUBJECT`/`$ENVIRONMENT` at evaluation time. Null means no attribute-based condition.
     * @param list<mixed> $arguments Positional arguments for the `$where` placeholders.
     */
    public function __construct(public Set $roles, public AuthorizationScope $scope, public ?string $where = null, public array $arguments = [])
    {
        $this->requiresOwner = $this->scope === AuthorizationScope::own;
    }

    /**
     * @param Set<string> $userRoles
     */
    public function allowsRoles(Set $userRoles): bool
    {
        return $this->roles->isEmpty || !$this->roles->isDisjoint($userRoles);
    }
}
