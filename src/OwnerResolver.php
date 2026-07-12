<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use ReflectionClass;
use ReflectionProperty;
use Sabatier\CoreData\ManagedObject;

/** @internal */
final class OwnerResolver
{
    /** @var array<class-string, ?string> */
    private static array $ownerFieldCache = [];
    private bool $isOwnerResolved = false;
    public ?Authorizable $owner {
        /**
         * @throws Exception
         */
        get {
            if ($this->isOwnerResolved) {
                return $this->owner;
            }
            $this->isOwnerResolved = true;
            $fieldName = self::getOwnerFieldName($this->resource::class);
            if ($fieldName === null) {
                return $this->owner = null;
            }
            $value = $this->resource->valueForKey($fieldName);
            return $this->owner = $value instanceof Authorizable ? $value : null;
        }
    }

    public function __construct(private readonly ManagedObject $resource)
    {
    }

    /**
     * @param class-string $className
     * @throws Exception
     */
    public static function getOwnerFieldName(string $className): ?string
    {
        if (array_key_exists($className, self::$ownerFieldCache)) {
            return self::$ownerFieldCache[$className];
        }
        $reflection = new ReflectionClass($className);
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getAttributes(Owner::class) !== []) {
                return self::$ownerFieldCache[$className] = $property->getName();
            }
        }
        return self::$ownerFieldCache[$className] = null;
    }
}
