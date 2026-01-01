<?php

namespace Sabatier\Service;

use Attribute;

/**
 * Marks a property as writable by specific roles under a defined scope.
 *
 * Usage example:
 *
 * <code>
 *  #[Writable(new Set(["Admin", "Manager"]), AuthorizationScope::all)]
 *  public float $discount;
 *
 *  #[Writable(new Set(["Admin"]), AuthorizationScope::own)]
 *  public ?string $note;
 * </code>
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Writable extends FieldAttribute
{
}
