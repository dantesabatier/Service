<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\CoreData\RelationshipDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Set;
use WeakMap;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\is_equal;

/**
 * Invalidates the outstanding JWT tokens of the authorizable entities whose roles, or whose roles' authorizations, a save changes.
 *
 * @see Authorizable::$refreshTokenVersion
 * @see JSONWebTokenVersionEvaluator
 */
final class AuthorizableTokenInvalidator
{
    private InterfaceImplementorResolver $implementorResolver {
        get => $this->implementorResolver ??= new InterfaceImplementorResolver($this->managedObjectModel);
    }
    /** @var class-string<ManagedObject> */
    private string $authorizableClass {
        get => $this->authorizableClass ??= $this->implementorResolver->resolve(Authorizable::class);
    }
    /** @var class-string<ManagedObject> */
    private string $authorizationClass {
        get => $this->authorizationClass ??= $this->implementorResolver->resolve(Authorization::class);
    }
    private RelationshipDescription $rolesRelationship {
        get {
            if (isset($this->rolesRelationship)) {
                return $this->rolesRelationship;
            }
            $entity = $this->managedObjectModel->entities->first(fn(EntityDescription $entity): bool => $entity->managedObjectClassName === $this->authorizableClass) ?? fatal_error("No entity found for class \"$this->authorizableClass\".");
            /** @var RelationshipDescription $relationship */
            $relationship = $entity->relationshipsByName["roles"] ?? fatal_error("Entity \"$entity->name\" has no \"roles\" relationship.");
            return $this->rolesRelationship = $relationship;
        }
    }
    private RelationshipDescription $authorizationsRelationship {
        get {
            if (isset($this->authorizationsRelationship)) {
                return $this->authorizationsRelationship;
            }
            $entity = $this->rolesRelationship->destinationEntity;
            /** @var RelationshipDescription $relationship */
            $relationship = $entity->relationshipsByName->first(fn(RelationshipDescription $relationship): bool => $relationship->destinationEntity->managedObjectClassName === $this->authorizationClass) ?? fatal_error("Entity \"$entity->name\" has no relationship to \"$this->authorizationClass\".");
            return $this->authorizationsRelationship = $relationship;
        }
    }
    /** @var WeakMap<ManagedObjectContext, Set<ManagedObject>> */
    private WeakMap $pendingAuthorizables {
        get => $this->pendingAuthorizables ??= new WeakMap();
    }

    public function __construct(private readonly ManagedObjectModel $managedObjectModel)
    {
    }

    /**
     * Collects the authorizable entities whose permissions the pending save of the context changes.
     *
     * @throws Exception
     */
    public function prepare(ManagedObjectContext $context): void
    {
        $committedContext = new ManagedObjectContext();
        $committedContext->persistentStoreCoordinator = $context->persistentStoreCoordinator;
        $changed = $context->insertedObjects->union($context->updatedObjects)->flatMap(fn(ManagedObject $object): Set => $this->affectedByChange($object, $committedContext));
        $deleted = $context->deletedObjects->flatMap(fn(ManagedObject $object): Set => $this->affectedByDeletion($object, $committedContext));
        $this->pendingAuthorizables[$context] = $changed->union($deleted)->filter(fn(ManagedObject $authorizable): bool => !$authorizable->isDeleted);
    }

    /**
     * Invalidates the tokens of the authorizable entities collected by {@see prepare()} for the context.
     *
     * @throws Exception
     */
    public function invalidate(ManagedObjectContext $context): void
    {
        /** @var Set<Authorizable> $authorizables */
        $authorizables = $this->pendingAuthorizables[$context] ?? new Set();
        unset($this->pendingAuthorizables[$context]);
        if ($authorizables->isEmpty) {
            return;
        }
        foreach ($authorizables as $authorizable) {
            $authorizable->refreshTokenVersion++;
        }
        $context->save();
    }

    /**
     * @return Set<ManagedObject>
     * @throws Exception
     */
    private function affectedByChange(ManagedObject $object, ManagedObjectContext $committedContext): Set
    {
        return match (true) {
            $object instanceof Authorizable => $this->changedRelatedObjects($object, $this->rolesRelationship, $committedContext)->isEmpty ? new Set() : new Set([$object]),
            $object instanceof AuthorizableRole => $this->affectedByRoleChange($object, $committedContext),
            $object instanceof Authorization => $this->affectedByAuthorizationChange($object, $committedContext),
            default => new Set(),
        };
    }

    /**
     * @return Set<ManagedObject>
     * @throws Exception
     */
    private function affectedByRoleChange(ManagedObject $role, ManagedObjectContext $committedContext): Set
    {
        $members = $this->hasChangedAttributes($role, new ArrayClass(["name"]), $committedContext) || !$this->changedRelatedObjects($role, $this->authorizationsRelationship, $committedContext)->isEmpty ? $this->members($role) : new Set();
        return $members->union($this->changedRelatedObjects($role, $this->rolesRelationship->inverseRelationship, $committedContext));
    }

    /**
     * @return Set<ManagedObject>
     * @throws Exception
     */
    private function affectedByAuthorizationChange(ManagedObject $authorization, ManagedObjectContext $committedContext): Set
    {
        /** @var Set<ManagedObject> $roles */
        $roles = $this->hasChangedAttributes($authorization, new ArrayClass(["name", "type", "scope"]), $committedContext) ? $this->roles($authorization) : new Set();
        return $roles->union($this->changedRelatedObjects($authorization, $this->authorizationsRelationship->inverseRelationship, $committedContext))->flatMap(fn(ManagedObject $role): Set => $this->members($role));
    }

    /**
     * @return Set<ManagedObject>
     * @throws Exception
     */
    private function affectedByDeletion(ManagedObject $object, ManagedObjectContext $committedContext): Set
    {
        return match (true) {
            $object instanceof AuthorizableRole => $this->committedRelatedObjects($object, $this->rolesRelationship->inverseRelationship, $committedContext),
            $object instanceof Authorization => $this->committedRelatedObjects($object, $this->authorizationsRelationship->inverseRelationship, $committedContext)->flatMap(fn(ManagedObject $role): Set => $this->members($role)),
            default => new Set(),
        };
    }

    /**
     * @param ArrayClass<string> $keys
     * @throws Exception
     */
    private function hasChangedAttributes(ManagedObject $object, ArrayClass $keys, ManagedObjectContext $committedContext): bool
    {
        $changedValues = $object->changedValuesForCurrentEvent();
        if ($keys->allSatisfy(fn(string $key): bool => !$changedValues->offsetExists($key))) {
            return false;
        }
        $committedObject = $this->committedObject($object, $committedContext);
        return $keys->contains(fn(string $key): bool => $changedValues->offsetExists($key) && !is_equal($changedValues[$key], $committedObject?->valueForKey($key)));
    }

    /**
     * @return Set<ManagedObject>
     * @throws Exception
     */
    private function changedRelatedObjects(ManagedObject $object, RelationshipDescription $relationship, ManagedObjectContext $committedContext): Set
    {
        if (!$object->changedValuesForCurrentEvent()->offsetExists($relationship->name)) {
            return new Set();
        }
        $objectIDs = new Set($object->objectIDsForRelationshipNamed($relationship->name))->symmetricDifference($this->committedObjectIDs($object, $relationship, $committedContext));
        return $objectIDs->map(fn(ManagedObjectID $objectID): ManagedObject => $object->managedObjectContext->object($objectID));
    }

    /**
     * @return Set<ManagedObject>
     * @throws Exception
     */
    private function committedRelatedObjects(ManagedObject $object, RelationshipDescription $relationship, ManagedObjectContext $committedContext): Set
    {
        return $this->committedObjectIDs($object, $relationship, $committedContext)->map(fn(ManagedObjectID $objectID): ManagedObject => $object->managedObjectContext->object($objectID));
    }

    /**
     * @return Set<ManagedObjectID>
     * @throws Exception
     */
    private function committedObjectIDs(ManagedObject $object, RelationshipDescription $relationship, ManagedObjectContext $committedContext): Set
    {
        return new Set($this->committedObject($object, $committedContext)?->objectIDsForRelationshipNamed($relationship->name) ?? []);
    }

    /**
     * @throws Exception
     */
    private function committedObject(ManagedObject $object, ManagedObjectContext $committedContext): ?ManagedObject
    {
        return $object->objectID->isTemporaryID ? null : $committedContext->existingObject($object->objectID);
    }

    /**
     * @return Set<ManagedObject>
     */
    private function members(ManagedObject $role): Set
    {
        /** @var Set<ManagedObject> */
        return $role->valueForKey($this->rolesRelationship->inverseRelationship->name);
    }

    /**
     * @return Set<ManagedObject>
     */
    private function roles(ManagedObject $authorization): Set
    {
        /** @var Set<ManagedObject> */
        return $authorization->valueForKey($this->authorizationsRelationship->inverseRelationship->name);
    }
}
