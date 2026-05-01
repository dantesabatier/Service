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
    protected bool $hasOwnScope {
        get => $this->fieldSecurityPolicy->hasOwnScope;
    }
    protected bool $isSecurityEnabled {
        get => $this->fieldSecurityPolicy->isSecurityEnabled;
    }

    public function __construct(Request $request, EntityDescription $entity, ManagedObjectContext $managedObjectContext, FieldSecurityPolicy $fieldSecurityPolicy)
    {
        parent::__construct($request);
        $this->entity = $entity;
        $this->managedObjectContext = $managedObjectContext;
        $this->fieldSecurityPolicy = $fieldSecurityPolicy;
    }

    protected function fetchRequestFor(ManagedObjectID|int $objectID): FetchRequest
    {
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
    protected function fetchBy(ManagedObjectID|int $objectID): ?ManagedObject
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
