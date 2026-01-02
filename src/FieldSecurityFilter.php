<?php

namespace Sabatier\Service;

use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;

/**
 * @psalm-type RulesCache = array<string, FieldRule>
 *
 * @internal
 */
final class FieldSecurityFilter
{
    /** @var array<class-string, array<class-string, RulesCache>> */
    private static array $metaCache = [];
    /** @var Set<string> */
    private readonly Set $userRoles;
    private OwnershipService $ownershipService {
        get => $this->ownershipService ??= new OwnershipService(new OwnerResolver($this->resource), $this->user);
    }

    public function __construct(private readonly ManagedObject $resource, private readonly Authorizable $user)
    {
        $this->userRoles = $this->user->roles->map(fn(AuthorizableRole $role): string => $role->name);
    }

    /**
     * @param class-string<ManagedObject> $className
     * @param class-string<Writable|Readable> $attributeClass
     * @return RulesCache
     * @throws ReflectionException
     */
    private static function getRules(string $className, string $attributeClass): array
    {
        if (isset(self::$metaCache[$className][$attributeClass])) {
            return self::$metaCache[$className][$attributeClass];
        }
        $rules = [];
        $reflection = new ReflectionClass($className);
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            foreach ($property->getAttributes($attributeClass) as $attribute) {
                /** @var Writable|Readable $meta */
                $meta = $attribute->newInstance();
                $rules[$property->getName()] = new FieldRule(new Set($meta->by), $meta->scope);
            }
        }
        self::$metaCache[$className] ??= [];
        return self::$metaCache[$className][$attributeClass] = $rules;
    }

    /**
     * @param Dictionary<mixed> $data
     * @param class-string<Writable|Readable> $attributeClass
     * @return Dictionary<mixed>
     * @throws ReflectionException
     */
    private function apply(Dictionary $data, string $attributeClass): Dictionary
    {
        $rules = self::getRules($this->resource::class, $attributeClass);
        if (empty($rules)) {
            return $data;
        }
        /** @var ArrayClass<string> $restrictedFields */
        $restrictedFields = new ArrayClass();
        $isOwnerResolved = false;
        $isOwner = false;
        foreach ($rules as $fieldName => $rule) {
            if (!$data->offsetExists($fieldName)) {
                continue;
            }
            if ($rule->allowsRoles($this->userRoles)) {
                continue;
            }
            if ($rule->requiresOwner) {
                if (!$isOwnerResolved) {
                    $isOwner = $this->ownershipService->isOwner;
                    $isOwnerResolved = true;
                }
                if ($isOwner) {
                    continue;
                }
            }
            $restrictedFields[] = $fieldName;
        }
        if ($restrictedFields->isEmpty) {
            return $data;
        }
        return $data->filter(fn(mixed $v, string $key): bool => !$restrictedFields->containsElement($key));
    }

    /**
     * @throws ReflectionException
     */
    public function filterRead(Dictionary $data): Dictionary
    {
        return $this->apply($data, Readable::class);
    }

    /**
     * @throws ReflectionException
     */
    public function filterWrite(Dictionary $data): Dictionary
    {
        return $this->apply($data, Writable::class);
    }
}
