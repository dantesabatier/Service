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
    private ArrayClass $readOnlyFields {
        get {
            if (isset($this->readOnlyFields)) {
                return $this->readOnlyFields;
            }
            $fields = new ArrayClass();
            $reflection = new ReflectionClass($this->resource);
            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                foreach ($property->getAttributes(Writable::class) as $attribute) {
                    /** @var Writable $writable */
                    $writable = $attribute->newInstance();
                    if (!new Set($writable->by)->isDisjoint($this->user->roles->map(fn(AuthorizableRole $role): string => $role->name))) {
                        continue;
                    }
                    if ($writable->scope === AuthorizationScope::own) {
                        $ownerResolver = new OwnerResolver($this->resource);
                        if ($ownerResolver->info->owner?->username !== $this->user->username) {
                            continue;
                        }
                    }
                    $fields[] = $property->getName();
                }
            }
            return $this->readOnlyFields = $fields;
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
        return $this->readOnlyFields->isEmpty ? $data : $data->filter(fn(mixed $value, string $key): bool => !$this->readOnlyFields->containsElement($key));
    }
}
