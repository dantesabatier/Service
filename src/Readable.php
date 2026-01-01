<?php

namespace Sabatier\Service;

use Attribute;

/**
 * Marks a property as readable by specific roles under a defined scope.
 *
 * Usage example:
 *
 * <code>
 *  #[Readable(["Admin", "Manager"], AuthorizationScope::all)]
 *  public float $discount;
 *
 *  #[Readable(["Admin"], AuthorizationScope::own)]
 *  public ?string $note;
 * </code>
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Readable
{
    /**
     * @param string[] $by List of role names allowed to read this property.
     * @param AuthorizationScope $scope Scope of the permission.
     */
    public function __construct(public array $by, public AuthorizationScope $scope)
    {
    }
}
