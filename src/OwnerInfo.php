<?php

namespace Sabatier\Service;

/**
 * Holds information about the owner property of a managed object.
 */
final readonly class OwnerInfo
{
    public function __construct(public string $propertyName, public ?Authorizable $owner)
    {
    }
}
