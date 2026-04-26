<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Attribute;

/**
 * Marks a property as defining the owner of a Resource.
 *
 *   Example usage:
 *
 *  <code>
 *   #[Owner]
 *   public ?User $createdBy;
 *  </code>
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Owner
{
}
