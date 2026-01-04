<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;

/** @internal */
abstract class PersistentSpaceResponseStrategy extends ResponseStrategy
{
    protected readonly EntityDescription $entity;
    protected readonly ManagedObjectContext $managedObjectContext;
    private readonly PersistentSpaceSecurityPolicy $securityPolicy;
    protected ?Authenticatable $user {
        get => $this->securityPolicy->user;
    }
    protected bool $hasOwnScope {
        get => $this->securityPolicy->hasOwnScope;
    }
    protected bool $isSecurityEnabled {
        get => $this->securityPolicy->isSecurityEnabled;
    }

    public function __construct(Request $request, EntityDescription $entity, ManagedObjectContext $managedObjectContext, AuthorizationContext $authorizationContext)
    {
        parent::__construct($request);
        $this->entity = $entity;
        $this->managedObjectContext = $managedObjectContext;
        $this->securityPolicy = new PersistentSpaceSecurityPolicy($authorizationContext->user, $authorizationContext->scopes, $authorizationContext->isSecurityEnabled);
    }

    protected function fetchRequestFor(ManagedObjectID|int $objectID): FetchRequest
    {
        /** @var FetchRequest<ManagedObject> $fetchRequest */
        $fetchRequest = new FetchRequest();
        $fetchRequest->entity = $this->entity;
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath(ServiceIdentity::identityKey), Expression::expressionForConstantValue($objectID));
        if ($serialization = $this->request->serialization) {
            $fetchRequest->serialization = $serialization;
        }
        return $fetchRequest;
    }

    /**
     * @throws Exception
     */
    protected function fetchBy(ManagedObjectID|int $objectID): ?ManagedObject
    {
        /** @var FetchRequest<ManagedObject> $fetchRequest */
        $fetchRequest = $this->fetchRequestFor($objectID);
        return $this->managedObjectContext->fetch($fetchRequest)->first;
    }

    protected function enforceOwnership(ManagedObject $object): void
    {
        $this->securityPolicy->enforceOwnership($object);
    }

    /**
     * @param ManagedObject $object
     * @param Dictionary<mixed> $body
     * @throws Exception
     */
    protected function applySecureUpdate(ManagedObject $object, Dictionary $body): void
    {
        $this->securityPolicy->applySecureUpdate($object, $body);
    }

    /**
     * @param ManagedObject $object
     * @param Dictionary<mixed> $data
     * @return Dictionary<mixed>
     * @throws Exception
     */
    protected function applySecureRead(ManagedObject $object, Dictionary $data): Dictionary
    {
        return $this->securityPolicy->applySecureRead($object, $data);
    }
}
