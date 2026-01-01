<?php

namespace Sabatier\Service;

use ReflectionClass;
use ReflectionProperty;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;

/** @internal */
final class FieldSecurityFilter
{
    private readonly OwnershipService $service;
    /** @var Set<string> */
    private readonly Set $userRoles;
    /** @var array<class-string<Readable|Writable>, ArrayClass<string>> */
    private array $cache = [];

    public function __construct(private readonly ManagedObject $resource, private readonly Authorizable $user)
    {
        $this->service = new OwnershipService(new OwnerResolver($this->resource), $this->user);
        $this->userRoles = $this->user->roles->map(fn(AuthorizableRole $role): string => $role->name);
    }

    /**
     * @param class-string<Readable|Writable> $attributeClass
     * @return ArrayClass<string>
     */
    private function getRestrictedFields(string $attributeClass): ArrayClass
    {
        if (isset($this->cache[$attributeClass])) {
            return $this->cache[$attributeClass];
        }
        /** @var ArrayClass<string> $fields */
        $fields = new ArrayClass();
        $reflection = new ReflectionClass($this->resource);
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            foreach ($property->getAttributes($attributeClass) as $attribute) {
                /** @var Writable|Readable $meta */
                $meta = $attribute->newInstance();
                if (!$meta->by->isDisjoint($this->userRoles)) {
                    continue;
                }
                if ($meta->scope === AuthorizationScope::own && $this->service->isOwner) {
                    continue;
                }
                $fields[] = $property->getName();
            }
        }
        return $this->cache[$attributeClass] = $fields;
    }

    /**
     * @param Dictionary<mixed> $data
     * @param ArrayClass<string> $restricted
     * @return Dictionary<mixed>
     */
    private function apply(Dictionary $data, ArrayClass $restricted): Dictionary
    {
        return $restricted->isEmpty ? $data : $data->filter(fn($v, $key) => !$restricted->containsElement($key));
    }

    /**
     * @param Dictionary<mixed> $data
     * @return Dictionary<mixed>
     */
    public function filterRead(Dictionary $data): Dictionary
    {
        return $this->apply($data, $this->getRestrictedFields(Readable::class));
    }

    /**
     * @param Dictionary<mixed> $data
     * @return Dictionary<mixed>
     */
    public function filterWrite(Dictionary $data): Dictionary
    {
        return $this->apply($data, $this->getRestrictedFields(Writable::class));
    }
}
