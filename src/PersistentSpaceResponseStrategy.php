<?php

namespace Sabatier\Service;

use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;

/** @internal */
abstract class PersistentSpaceResponseStrategy extends ResponseStrategy
{
    protected readonly EntityDescription $entity;
    protected readonly ManagedObjectContext $managedObjectContext;
    protected readonly AuthorizationContext $authorizationContext;

    public function __construct(Request $request, EntityDescription $entity, ManagedObjectContext $managedObjectContext, AuthorizationContext $authorizationContext)
    {
        parent::__construct($request);
        $this->entity = $entity;
        $this->managedObjectContext = $managedObjectContext;
        $this->authorizationContext = $authorizationContext;
    }

    protected function enforceOwnership(ManagedObject $object): void
    {
        if ($this->authorizationContext->scopes->contains(fn(string $scope): bool => str_ends_with($scope, AuthorizationScope::own->name))) {
            $service = new OwnershipService(new OwnerResolver($object), $this->authorizationContext->user);
            $service->isOwner ?: throw new ForbiddenException();
        }
    }

    protected function managedObject(ManagedObjectID|int $objectID): ?ManagedObject
    {
        /** @var FetchRequest<ManagedObject> $fetchRequest */
        $fetchRequest = new FetchRequest();
        $fetchRequest->entity = $this->entity;
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath(ServiceIdentity::identityKey), Expression::expressionForConstantValue($objectID));
        if ($serialization = $this->request->serialization) {
            $fetchRequest->serialization = $serialization;
        }
        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->managedObjectContext->fetch($fetchRequest)->first;
    }
}
