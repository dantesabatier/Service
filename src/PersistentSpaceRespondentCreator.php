<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\SQLEntity;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;

/** @internal */
class PersistentSpaceRespondentCreator extends PersistentSpaceRespondent
{
    public Response $response {
        get {
            /** @var PersistentSpace $responder */
            $responder = $this->responder;
            $request = $responder->request;
            $body = $request->parsedBody;
            $context = $responder->managedObjectContext;
            $entity = $responder->entity;
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
            $responder->content = json_encode($object?->serialized($responder->request->serialization), JSON_PRESERVE_ZERO_FRACTION);
            $responder->headerFields["Content-Type"] = "application/json";
            return $this->response;
        }
    }
}
