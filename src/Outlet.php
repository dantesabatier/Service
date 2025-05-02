<?php

namespace Sabatier\Service;

use Attribute;

/**
 * Attribute used to mark a property as visible to a view's renderer.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Outlet
{
}
