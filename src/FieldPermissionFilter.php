<?php

namespace Sabatier\Service;

use ReflectionClass;
use ReflectionProperty;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;

/** @internal */
final class FieldPermissionFilter
{
    /** @var ArrayClass<string> */
    private ArrayClass $unwritableFields {
        get {
            if (isset($this->unwritableFields)) {
                return $this->unwritableFields;
            }
            $fields = new ArrayClass();
            $ownership = new OwnershipService(new OwnerResolver($this->resource), $this->user);
            $userRoles = $this->user->roles->map(fn(AuthorizableRole $role): string => $role->name);
            $reflection = new ReflectionClass($this->resource);
            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                foreach ($property->getAttributes(Writable::class) as $attribute) {
                    /** @var Writable $writable */
                    $writable = $attribute->newInstance();
                    if (!new Set($writable->by)->isDisjoint($userRoles)) {
                        continue;
                    }
                    if ($writable->scope === AuthorizationScope::own && $ownership->isOwner) {
                        continue;
                    }
                    $fields[] = $property->getName();
                }
            }
            return $this->unwritableFields = $fields;
        }
    }

    public function __construct(private readonly ManagedObject $resource, private readonly Authenticatable $user)
    {
    }

    /**
     * @param Dictionary<mixed> $data
     * @return Dictionary<mixed>
     */
    public function filter(Dictionary $data): Dictionary
    {
        return $this->unwritableFields->isEmpty ? $data : $data->filter(fn(mixed $value, string $key): bool => !$this->unwritableFields->containsElement($key));
    }
}
