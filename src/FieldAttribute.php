<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * Base security attribute for property-level access control.
 *
 * This abstract class provides a unified structure for metadata-driven security.
 */
abstract readonly class FieldAttribute
{
    /**
     * @param list<string> $by Role names allowed to access this property. Empty means any role is allowed.
     * @param AuthorizationScope $scope Scope of the permission.
     */
    public function __construct(public array $by = [], public AuthorizationScope $scope = AuthorizationScope::all)
    {
    }
}
