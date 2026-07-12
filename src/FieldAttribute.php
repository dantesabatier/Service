<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * Base security attribute for property-level access control.
 *
 * This abstract class provides a unified structure for metadata-driven security. Beyond role membership (`$by`) and ownership scope (`$scope`), a property may carry an attribute-based condition (`$where`) expressed as a predicate format string evaluated against the resource, with temporal substitution variables available.
 */
abstract readonly class FieldAttribute
{
    /**
     * @param list<string> $by Role names allowed to access this property. Empty means any role is allowed.
     * @param AuthorizationScope $scope Scope of the permission.
     * @param string|null $where Predicate format string gating access to this property, evaluated against the resource with temporal substitution variables. Null means no attribute-based condition.
     * @param list<mixed> $arguments Positional arguments substituted into `$where`, one per format placeholder in order.
     */
    public function __construct(public array $by = [], public AuthorizationScope $scope = AuthorizationScope::all, public ?string $where = null, public array $arguments = [])
    {
    }
}
