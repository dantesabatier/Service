<?php

namespace Sabatier\Service;

use ReflectionClass;
use ReflectionProperty;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;

/** @internal */
final class FieldReadPermissionFilter
{
    /** @var ArrayClass<string> */
    private ArrayClass $unreadableFields {
        get {
            if (isset($this->unreadableFields)) {
                return $this->unreadableFields;
            }
            $fields = new ArrayClass();
            $reflection = new ReflectionClass($this->resource);
            $ownership = new OwnershipService(new OwnerResolver($this->resource), $this->user);
            $userRoles = $this->user->roles->map(fn(AuthorizableRole $role): string => $role->name);
            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                foreach ($property->getAttributes(Readable::class) as $attribute) {
                    /** @var Readable $readable */
                    $readable = $attribute->newInstance();
                    if (!new Set($readable->by)->isDisjoint($userRoles)) {
                        continue;
                    }
                    if ($readable->scope === AuthorizationScope::own && $ownership->isOwner) {
                        continue;
                    }
                    $fields[] = $property->getName();
                }
            }
            return $this->unreadableFields = $fields;
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
        return $this->unreadableFields->isEmpty ? $data : $data->filter(fn(mixed $value, string $key): bool => !$this->unreadableFields->containsElement($key));
    }
}
