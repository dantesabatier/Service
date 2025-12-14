<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\SQLEntity;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;

/** @internal */
final class CreatePersistentSpaceResponseStrategy extends PersistentSpaceResponseStrategy
{
    public Response $response {
        /**
         * @throws Exception
         */
        get {
            $request = $this->request;
            $body = $request->parsedBody;
            $context = $this->managedObjectContext;
            $entity = $this->entity;
            if ($objectID = $body[SQLEntity::primaryKeyName]) {
                /** @var FetchRequest<Number> $fetchRequest */
                $fetchRequest = new FetchRequest();
                $fetchRequest->entity = $entity;
                $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath(SQLEntity::primaryKeyName), Expression::expressionForConstantValue($objectID));
                $fetchRequest->resultType = FetchRequestResultType::countResultType;
                if ($context->count($fetchRequest)) {
                    throw new ConflictException();
                }
            }
            $object = EntityDescription::insertNewObject($entity->name, $context);
            $object->setValuesForKeys($body);
            $context->save();
            $object = $this->managedObject($object->objectID);
            return new Response($request->url, HTTPStatusCode::created, body: $object?->serialized($this->request->serialization));
        }
    }
}
