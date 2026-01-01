<?php

namespace Sabatier\Service;

/** @internal */
final readonly class OwnerInfo
{
    public function __construct(public string $propertyName, public ?Authorizable $owner)
    {
    }
}
