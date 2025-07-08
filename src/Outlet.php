<?php

namespace Sabatier\Service;

use Attribute;

/**
 * An attribute to mark a property as visible to the view controller context.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Outlet
{
}
