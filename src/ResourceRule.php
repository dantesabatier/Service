<?php

declare(strict_types=1);

namespace Sabatier\Service;

use ReflectionClass;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Set;

/**
 * Resource-level access rule read from a `#[Readable]` or `#[Writable]` attribute placed on a
 * managed object class. Unlike {@see FieldRule}, which gates a single field within an
 * already-authorized row, a resource rule gates the row as a whole.
 *
 * @psalm-type RuleCache = ResourceRule|null
 *
 * @internal
 */
final readonly class ResourceRule
{
    /** @var array<class-string, array<class-string, RuleCache>> */
    private static array $cache = [];

    public bool $requiresOwner;

    /**
     * @param Set<string> $roles Role names allowed to access the resource. Empty means any role.
     * @param AuthorizationScope $scope Scope of the permission.
     * @param string|null $where Predicate format string gating the resource, resolved against the row with `$SUBJECT`/`$ENVIRONMENT`. Null means no attribute-based condition.
     * @param list<mixed> $arguments Positional arguments for the `$where` placeholders.
     */
    public function __construct(public Set $roles, public AuthorizationScope $scope, public ?string $where = null, public array $arguments = [])
    {
        $this->requiresOwner = $this->scope === AuthorizationScope::own;
    }

    /**
     * @param Set<string> $userRoles
     */
    public function allowsRoles(Set $userRoles): bool
    {
        return $this->roles->isEmpty || !$this->roles->isDisjoint($userRoles);
    }

    /**
     * Resolves the resource rule declared on a managed object class for a given security attribute, or null when the class carries none.
     *
     * @param class-string<ManagedObject> $className
     * @param class-string<Writable|Readable> $attributeClass
     */
    public static function resolve(string $className, string $attributeClass): ?ResourceRule
    {
        if (array_key_exists($attributeClass, self::$cache[$className] ?? [])) {
            return self::$cache[$className][$attributeClass];
        }
        $rule = null;
        foreach (new ReflectionClass($className)->getAttributes($attributeClass) as $attribute) {
            /** @var Writable|Readable $securityAttribute */
            $securityAttribute = $attribute->newInstance();
            $rule = new ResourceRule(new Set($securityAttribute->by), $securityAttribute->scope, $securityAttribute->where, $securityAttribute->arguments);
        }
        self::$cache[$className] ??= [];
        return self::$cache[$className][$attributeClass] = $rule;
    }
}
