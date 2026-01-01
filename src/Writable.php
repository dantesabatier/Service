<?php

namespace Sabatier\Service;

use Attribute;

/**
 * Marks a property as writable by specific roles under a defined scope.
 *
 * Usage example:
 *
 * <code>
 *  #[Writable(["Admin", "Manager"], AuthorizationScope::all)]
 *  public float $discount;
 *
 *  #[Writable(["Admin"], AuthorizationScope::own)]
 *  public ?string $note;
 * </code>
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Writable
{
    /**
     * @param string[] $by List of role names allowed to write this property.
     * @param AuthorizationScope $scope Scope of the permission.
     */
    public function __construct(public array $by, public AuthorizationScope $scope)
    {
    }
}
