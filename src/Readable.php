<?php

namespace Sabatier\Service;

use Attribute;
use Sabatier\Foundation\Set;

/**
 * Marks a property as readable by specific roles under a defined scope.
 *
 * Usage example:
 *
 * <code>
 *  #[Readable(new Set(["Admin", "Manager"]), AuthorizationScope::all)]
 *  public float $discount;
 *
 *  #[Readable(new Set(["Admin"]), AuthorizationScope::own)]
 *  public ?string $note;
 * </code>
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Readable
{
    /**
     * @param Set<string> $by List of role names allowed to read this property.
     * @param AuthorizationScope $scope Scope of the permission.
     */
    public function __construct(public Set $by, public AuthorizationScope $scope)
    {
    }
}
