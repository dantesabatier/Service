<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Attribute;

/**
 * Marks a property — or an entire resource — as writable by specific roles under a defined scope.
 *
 * On a property, the rule gates writes to that single field. On a class, the rule gates the
 * resource as a whole: a create, update, or delete of a row the subject cannot write is
 * rejected with `403 Forbidden`.
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
 *  #[Writable(where: "dueDate >= $TODAY")]                      // temporal condition
 *  public float $amount;
 *
 *  #[Writable(["Admin"], where: "status != %@", arguments: ["locked"])] // resource-level
 *  final class Invoice extends ManagedObject { }
 * </code>
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
final readonly class Writable extends FieldAttribute
{
}
