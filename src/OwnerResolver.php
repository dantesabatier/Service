<?php

namespace Sabatier\Service;

use ReflectionClass;
use ReflectionProperty;
use Sabatier\CoreData\ManagedObject;

/** @internal */
final class OwnerResolver
{
    private bool $isOwnerResolved = false;
    public ?Authorizable $owner {
        get {
            if ($this->isOwnerResolved) {
                return $this->owner;
            }
            $this->isOwnerResolved = true;
            $reflection = new ReflectionClass($this->resource);
            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->getAttributes(Owner::class) !== []) {
                    $key = $property->getName();
                    $value = $this->resource->$key;
                    return $this->owner = $value instanceof Authorizable ? $value : null;
                }
            }
            return $this->owner = null;
        }
    }

    public function __construct(private readonly ManagedObject $resource)
    {
    }
}
