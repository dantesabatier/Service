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
    /** @var array<string, ArrayClass<string>> */
    private static array $reflectionCache = [];

    public function __construct(private readonly ManagedObject $resource, private readonly Authorizable $user)
    {
        $this->service = new OwnershipService(new OwnerResolver($this->resource), $this->user);
        $this->userRoles = $this->user->roles->map(fn(AuthorizableRole $role): string => $role->name);
    }

    /**
     * @param class-string<Writable|Readable> $attributeClass
     * @return ArrayClass<string>
     */
    private function getRestrictedFields(string $attributeClass): ArrayClass
    {
        $cacheKey = $this->resource::class . ':' . $attributeClass;
        if (isset(self::$reflectionCache[$cacheKey])) {
            return self::$reflectionCache[$cacheKey];
        }
        /** @var ArrayClass<string> $fields */
        $fields = new ArrayClass();
        $reflection = new ReflectionClass($this->resource);
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            foreach ($property->getAttributes($attributeClass) as $attribute) {
                /** @var Writable|Readable $meta */
                $meta = $attribute->newInstance();
                if (!new Set($meta->by)->isDisjoint($this->userRoles)) {
                    continue;
                }
                if ($meta->scope === AuthorizationScope::own && $this->service->isOwner) {
                    continue;
                }
                $fields[] = $property->getName();
            }
        }
        return self::$reflectionCache[$cacheKey] = $fields;
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
