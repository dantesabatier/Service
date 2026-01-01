<?php

namespace Sabatier\Service;

use Attribute;

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
final readonly class Readable extends FieldAttribute
{
}
