<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
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
            if ($objectID = $body[ServiceIdentity::identityKey]) {
                /** @var FetchRequest<Number> $fetchRequest */
                $fetchRequest = new FetchRequest();
                $fetchRequest->entity = $entity;
                $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath(ServiceIdentity::identityKey), Expression::expressionForConstantValue($objectID));
                $fetchRequest->resultType = FetchRequestResultType::countResultType;
                if ($context->count($fetchRequest)) {
                    throw new ConflictException();
                }
            }
            $object = EntityDescription::insertNewObject($entity->name, $context);
            $this->applySecureUpdate($object, $body);
            $context->save();
            $object = $this->fetchBy($object->objectID);
            return new Response($request->url, HTTPStatusCode::created, body: $object?->serialized($this->request->serialization));
        }
    }
}
