<?php

namespace Sabatier\Service;

/**
 * Base security attribute for property-level access control.
 *
 * This abstract class provides a unified structure for metadata-driven security.
 */
abstract readonly class FieldAttribute
{
    /**
     * @param list<string> $by list of role names allowed to write this property.
     * @param AuthorizationScope $scope Scope of the permission.
     */
    public function __construct(public array $by, public AuthorizationScope $scope)
    {
    }
}
