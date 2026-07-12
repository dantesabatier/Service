<?php

declare(strict_types=1);

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
use Sabatier\Foundation\Predicates\Predicate;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

/** @internal */
abstract class PersistentSpaceResponseStrategy extends ResponseStrategy
{
    protected readonly EntityDescription $entity;
    protected readonly ManagedObjectContext $managedObjectContext;
    private readonly FieldSecurityPolicy $fieldSecurityPolicy;
    protected ?Authorizable $user {
        get => $this->fieldSecurityPolicy->user;
    }
    protected bool $isSecurityEnabled {
        get => $this->fieldSecurityPolicy->isSecurityEnabled;
    }

    protected function hasOwnScopeFor(string $entityName): bool
    {
        return $this->fieldSecurityPolicy->hasOwnScopeFor($entityName);
    }

    public function __construct(Request $request, EntityDescription $entity, ManagedObjectContext $managedObjectContext, FieldSecurityPolicy $fieldSecurityPolicy)
    {
        parent::__construct($request);
        $this->entity = $entity;
        $this->managedObjectContext = $managedObjectContext;
        $this->fieldSecurityPolicy = $fieldSecurityPolicy;
    }

    protected function fetchRequestFor(ManagedObjectID|int|string $objectID): FetchRequest
    {
        if (is_string($objectID) && is_numeric($objectID)) {
            $objectID = (int)$objectID;
        }
        /** @var FetchRequest<ManagedObject> $fetchRequest */
        $fetchRequest = new FetchRequest();
        $fetchRequest->entity = $this->entity;
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath(ManagedObjectObjectIDKey), Expression::expressionForConstantValue($objectID));
        if ($serialization = $this->request->serialization) {
            $fetchRequest->serialization = $serialization;
        }
        return $fetchRequest;
    }

    /**
     * @throws Exception
     */
    protected function fetchBy(ManagedObjectID|int|string $objectID): ?ManagedObject
    {
        /** @var FetchRequest<ManagedObject> $fetchRequest */
        $fetchRequest = $this->fetchRequestFor($objectID);
        return $this->managedObjectContext->fetch($fetchRequest)->first;
    }

    protected function enforceOwnership(ManagedObject $object): void
    {
        $this->fieldSecurityPolicy->enforceOwnership($object);
    }

    /**
     * @throws Exception
     */
    protected function enforceResourceAccess(ManagedObject $object): void
    {
        $this->fieldSecurityPolicy->enforceResourceAccess($object);
    }

    /**
     * Resolves the attribute-based read predicate for the backing class, or null when there is nothing to narrow.
     *
     * @param class-string<ManagedObject> $className
     */
    protected function resourceReadPredicate(string $className): ?Predicate
    {
        return $this->fieldSecurityPolicy->resourceReadPredicate($className);
    }

    /**
     * @param ManagedObject $object
     * @param Dictionary<mixed> $body
     * @throws Exception
     */
    protected function applySecureUpdate(ManagedObject $object, Dictionary $body): void
    {
        $this->fieldSecurityPolicy->applySecureUpdate($object, $body);
    }

    /**
     * @param ManagedObject $object
     * @param Dictionary<mixed> $data
     * @return Dictionary<mixed>
     * @throws Exception
     */
    protected function applySecureRead(ManagedObject $object, Dictionary $data): Dictionary
    {
        return $this->fieldSecurityPolicy->applySecureRead($object, $data);
    }
}
