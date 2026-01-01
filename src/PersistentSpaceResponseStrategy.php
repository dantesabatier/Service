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
    public readonly ManagedObjectContext $managedObjectContext;
    public readonly EntityDescription $entity;

    public function __construct(Request $request, EntityDescription $entity, ManagedObjectContext $managedObjectContext)
    {
        parent::__construct($request);
        $this->entity = $entity;
        $this->managedObjectContext = $managedObjectContext;
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
