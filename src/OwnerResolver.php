<?php

namespace Sabatier\Service;

use ReflectionClass;
use ReflectionProperty;
use Sabatier\CoreData\ManagedObject;

/** @internal */
final class OwnerResolver
{
    public OwnerInfo $info {
        get {
            if (isset($this->info)) {
                return $this->info;
            }
            $reflection = new ReflectionClass($this->resource);
            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->getAttributes(Owner::class) !== []) {
                    (void)$property->setAccessible(true);
                    $value = $property->getValue($this->resource);
                    return $this->info = new OwnerInfo($property->getName(), $value instanceof Authorizable ? $value : null);
                }
            }
            return $this->info = new OwnerInfo("", null);
        }
    }

    public function __construct(private readonly ManagedObject $resource)
    {
    }
}
