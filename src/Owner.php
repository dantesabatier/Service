<?php

namespace Sabatier\Service;

use Attribute;

/**
 * Marks a property as defining the owner of a Resource.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Owner
{
}
