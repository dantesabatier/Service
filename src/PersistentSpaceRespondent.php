<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\CoreData\SQLEntity;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;

/** @internal */
abstract class PersistentSpaceRespondent extends Respondent
{
    public function __construct(PersistentSpace $persistentSpace)
    {
        parent::__construct($persistentSpace);
    }

    public function managedObject(ManagedObjectID|int $objectID): ?ManagedObject
    {
        /** @var PersistentSpace $responder */
        $responder = $this->responder;
        /** @var FetchRequest<ManagedObject> $fetchRequest */
        $fetchRequest = new FetchRequest();
        $fetchRequest->entity = $responder->entity;
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath(SQLEntity::primaryKeyName), Expression::expressionForConstantValue($objectID));
        if ($serialization = $responder->request->serialization) {
            $fetchRequest->serialization = $serialization;
        }
        /** @noinspection PhpUnhandledExceptionInspection */
        return $responder->managedObjectContext->fetch($fetchRequest)->first;
    }
}
