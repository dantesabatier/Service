<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\CoreData\SQLEntity;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\URLComponents;
use Sabatier\Foundation\URLQueryItem;

/** @internal */
abstract class PersistentSpaceRespondent extends Respondent
{
    private(set) Dictionary $body;

    public function __construct(PersistentSpace $persistentSpace)
    {
        parent::__construct($persistentSpace);
        $request = $persistentSpace->request;
        $this->body = Dictionary::dictionaryWithArray($request->getParsedBody());
        if ($this->body->isEmpty) {
            $components = new URLComponents($request->url->absoluteString);
            if ($dictionary = $components->queryItems?->reduce(new Dictionary(), function (Dictionary $result, URLQueryItem $queryItem): Dictionary {
                $result[$queryItem->name] = $queryItem->value;
                return $result;
            })) {
                $this->body->merge($dictionary);
            }
        }
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
