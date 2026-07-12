<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Attribute;

/**
 * Marks a property as readable by specific roles under a defined scope.
 *
 * Usage example:
 *
 * <code>
 *  #[Readable]                                                    // any role
 *  public string $name;
 *
 *  #[Readable(["Admin", "Manager"], AuthorizationScope::all)]
 *  public float $discount;
 *
 *  #[Readable(["Admin"], AuthorizationScope::own)]
 *  public ?string $note;
 *
 *  #[Readable(where: "status == %@", arguments: ["published"])] // attribute-based
 *  public string $body;
 *
 *  #[Readable(where: "department == $SUBJECT.department")]      // resource vs subject
 *  public float $salary;
 * </code>
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Readable extends FieldAttribute
{
}
