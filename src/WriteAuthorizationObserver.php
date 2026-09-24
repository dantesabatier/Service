<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\RelationshipDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Notification;
use Sabatier\Foundation\Set;
use function Sabatier\Foundation\localized_string;
use const Sabatier\CoreData\UnknownName;

/** @internal */
final readonly class WriteAuthorizationObserver
{
    /**
     * @param AuthorizationService $authorizationService
     */
    public function __construct(private AuthorizationService $authorizationService)
    {
    }

    /**
     * @throws ForbiddenException
     * @throws Exception
     */
    public function contextWillSave(Notification $notification): void
    {
        $context = RequestSecurityContext::current();
        if ($context === null || !$context->enforcesWrites || RequestSecurityContext::isPerformingAsSystem() || $notification->object !== $context->managedObjectContext) {
            return;
        }
        $user = $context->user;
        if ($user === null) {
            return;
        }
        $managedObjectContext = $context->managedObjectContext;
        $policy = $context->fieldSecurityPolicy;
        foreach ($managedObjectContext->insertedObjects as $object) {
            $this->authorize($context, $user, $object, AuthorizationType::create);
            $policy->enforceOwnership($object);
            $policy->enforceResourceAccess($object);
            $policy->enforceFieldWriteAccess($object, true);
            $this->authorizeLinks($context, $user, $object);
        }
        foreach ($managedObjectContext->updatedObjects as $object) {
            if ($this->mirrorsLinksMadeElsewhere($object)) {
                continue;
            }
            $this->authorize($context, $user, $object, AuthorizationType::update);
            $policy->enforceCommittedWriteAccess($object);
            $policy->enforceOwnership($object);
            $policy->enforceFieldWriteAccess($object, false);
            $this->authorizeLinks($context, $user, $object);
        }
        foreach ($managedObjectContext->deletedObjects as $object) {
            $this->authorize($context, $user, $object, AuthorizationType::delete);
            $policy->enforceCommittedWriteAccess($object);
        }
    }

    /**
     * @throws ForbiddenException
     * @throws Exception
     */
    private function authorize(RequestSecurityContext $context, Authorizable $user, ManagedObject $object, AuthorizationType $action): void
    {
        $entityName = $object->entity->name;
        $this->authorizationService->isAuthorized($user, $entityName, $action, $context->scopes, $context->managedObjectContext) ?: throw new ForbiddenException(sprintf(match ($action) {
            AuthorizationType::create => localized_string("You don't have permission to create \"%s\" rows."),
            AuthorizationType::delete => localized_string("You don't have permission to delete \"%s\" rows."),
            default => localized_string("You don't have permission to update \"%s\" rows."),
        }, $entityName));
    }

    /**
     * @throws ForbiddenException
     * @throws Exception
     */
    private function authorizeLinks(RequestSecurityContext $context, Authorizable $user, ManagedObject $object): void
    {
        $relationships = $object->entity->relationshipsByName;
        foreach ($object->changedValues() as $key => $value) {
            if (!$relationships[$key] instanceof RelationshipDescription) {
                continue;
            }
            $previous = $object->isInserted ? new ArrayClass() : $this->members($object->committedValues(new ArrayClass([$key]))[$key]);
            $added = $this->members($value)->filter(fn(ManagedObject $member): bool => !$previous->containsElement($member) && !$member->isInserted && !$member->isDeleted);
            foreach ($added as $member) {
                $entityName = $member->entity->name;
                $this->authorizationService->isAuthorized($user, $entityName, AuthorizationType::read, $context->scopes, $context->managedObjectContext) && $context->fieldSecurityPolicy->isRowReadable($member) ?: throw new ForbiddenException(sprintf(localized_string("You don't have permission to read \"%s\"."), $entityName));
            }
        }
    }

    /**
     * Only the to-many side of a to-one relationship is exempt, so the two sides of a link can never exempt each other.
     *
     * @throws Exception
     */
    private function mirrorsLinksMadeElsewhere(ManagedObject $object): bool
    {
        $changedValues = $object->changedValues();
        if ($changedValues->isEmpty) {
            return false;
        }
        $relationships = $object->entity->relationshipsByName;
        foreach ($changedValues as $key => $value) {
            $relationship = $relationships[$key];
            if (!$relationship instanceof RelationshipDescription || !$relationship->isToMany || $relationship->lazyInverseRelationshipName === UnknownName || $relationship->inverseRelationship->isToMany) {
                return false;
            }
            $inverseName = $relationship->inverseRelationship->name;
            $current = $this->members($value);
            $previous = $this->members($object->committedValues(new ArrayClass([$key]))[$key]);
            $touched = $current->filter(fn(ManagedObject $member): bool => !$previous->containsElement($member))
                ->appendingContentsOf($previous->filter(fn(ManagedObject $member): bool => !$current->containsElement($member)));
            if ($touched->contains(fn(ManagedObject $member): bool => !$member->isDeleted && !$member->changedValues()->offsetExists($inverseName))) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return ArrayClass<ManagedObject>
     */
    private function members(mixed $value): ArrayClass
    {
        if ($value instanceof ManagedObject) {
            return new ArrayClass([$value]);
        }
        if (!$value instanceof Set && !$value instanceof ArrayClass) {
            return new ArrayClass();
        }
        /** @var ArrayClass<mixed> $members */
        $members = new ArrayClass($value->array);
        /** @var ArrayClass<ManagedObject> */
        return $members->filter(fn(mixed $member): bool => $member instanceof ManagedObject);
    }
}
