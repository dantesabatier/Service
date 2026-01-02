<?php

namespace Sabatier\Service;

use Attribute;

/**
 * Marks a property as accessible from the view controller context.
 *
 * Properties annotated with this attribute are intended to be connected to
 * interface elements or resources within a view. This allows the framework
 * to automatically bind UI components to the corresponding properties in
 * the controller.
 *
 * Example usage:
 *
 * <code>
 *     #[Outlet]
 *    public string $labelText;
 * </code>
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Outlet
{
}
