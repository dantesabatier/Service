<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Attribute;

/**
 * Marks a property — or an entire resource — as readable by specific roles under a defined scope.
 *
 * On a property, the rule gates that single field within an already-readable row.
 * On a class, the rule gates the resource as a whole: rows the subject cannot read are
 * excluded at fetch time (the condition is folded into the query predicate), so a listing
 * omits them and a read by id yields nothing.
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
 *
 *  #[Readable(["Finance"], where: "createdAt BETWEEN $ENVIRONMENT.range")] // resource-level
 *  final class Invoice extends ManagedObject { }
 * </code>
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
final readonly class Readable extends FieldAttribute
{
}
