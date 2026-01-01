<?php

namespace Sabatier\Service;

use Attribute;
use Sabatier\Foundation\Set;

/**
 * Base security attribute for property-level access control.
 *
 * This abstract class provides a unified structure for metadata-driven security.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
abstract readonly class FieldAttribute
{
    /**
     * @param Set<string> $by Set of role names allowed to write this property.
     * @param AuthorizationScope $scope Scope of the permission.
     */
    public function __construct(public Set $by, public AuthorizationScope $scope)
    {
    }
}
