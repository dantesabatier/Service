<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObject;
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
            $parsedBody = $request->parsedBody;
            $context = $this->managedObjectContext;
            $entity = $this->entity;
            if ($objectID = $parsedBody[ServiceIdentity::identityKey]) {
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
            $this->applySecureUpdate($object, $parsedBody);
            $context->save();
            /** @var ManagedObject $refreshed */
            $refreshed = $this->fetchBy($object->objectID);
            $body = $this->applySecureRead($refreshed, $refreshed->jsonSerialize());
            return new Response($request->url, HTTPStatusCode::created, body: $body);
        }
    }
}
