<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Attribute;

/**
 * Marks a property as writable by specific roles under a defined scope.
 *
 * Usage example:
 *
 * <code>
 *  #[Writable]                                                    // any role
 *  public string $name;
 *
 *  #[Writable(["Admin", "Manager"], AuthorizationScope::all)]
 *  public float $discount;
 *
 *  #[Writable(["Admin"], AuthorizationScope::own)]
 *  public ?string $note;
 *
 *  #[Writable(where: "status == %@", arguments: ["draft"])]     // attribute-based
 *  public string $body;
 *
 *  #[Writable(where: "department == $SUBJECT.department")]      // resource vs subject
 *  public float $salary;
 * </code>
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Writable extends FieldAttribute
{
}
