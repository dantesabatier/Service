<?php

namespace Sabatier\Service;

use ReflectionClass;
use ReflectionProperty;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;

/** @internal */
final readonly class FieldSecurityFilter
{
    private OwnershipService $ownership;
    /** @var ArrayClass<string> */
    private ArrayClass $userRoles;

    public function __construct(private ManagedObject $resource, private Authenticatable $user)
    {
        $this->ownership = new OwnershipService(new OwnerResolver($this->resource), $this->user);
        $this->userRoles = $this->user->roles->map(fn(AuthorizableRole $r) => $r->name);
    }

    /**
     * @param class-string<Readable|Writable> $attributeClass
     */
    private function getRestrictedFields(string $attributeClass): ArrayClass
    {
        static $cache = [];
        if (isset($cache[$attributeClass])) {
            return $cache[$attributeClass];
        }
        $fields = new ArrayClass();
        $reflection = new ReflectionClass($this->resource);
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            foreach ($property->getAttributes($attributeClass) as $attribute) {
                $meta = $attribute->newInstance();
                if (!new Set($meta->by)->isDisjoint($this->userRoles)) {
                    continue;
                }
                if ($meta->scope === AuthorizationScope::own && $this->ownership->isOwner) {
                    continue;
                }
                $fields[] = $property->getName();
            }
        }
        return $cache[$attributeClass] = $fields;
    }

    private function apply(Dictionary $data, ArrayClass $restricted): Dictionary
    {
        return $restricted->isEmpty ? $data : $data->filter(fn($v, $key) => !$restricted->containsElement($key));
    }

    public function filterRead(Dictionary $data): Dictionary
    {
        return $this->apply($data, $this->getRestrictedFields(Readable::class));
    }

    public function filterWrite(Dictionary $data): Dictionary
    {
        return $this->apply($data, $this->getRestrictedFields(Writable::class));
    }
}
