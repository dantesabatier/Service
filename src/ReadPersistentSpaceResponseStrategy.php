<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\CompoundPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\Predicate;

/** @internal */
final class ReadPersistentSpaceResponseStrategy extends PersistentSpaceResponseStrategy
{
    public FetchRequest $fetchRequest {
        /**
         * @throws Exception
         */
        get {
            $fetchRequest = new RequestToFetchRequestAdapter($this->request, $this->managedObjectContext)->fetchRequest;
            $fetchRequest->entity = $this->entity;
            $entityClassName = $this->entity->managedObjectClassName ?? $this->entity->name;
            if ($this->isSecurityEnabled && $this->hasOwnScopeFor($this->entity->name) && ($ownerKey = OwnerResolver::getOwnerFieldName($entityClassName))) {
                $this->narrow($fetchRequest, new ComparisonPredicate(Expression::expressionForKeyPath($ownerKey), Expression::expressionForConstantValue($this->user)));
            }
            $constraint = $this->resourceReadConstraint($entityClassName);
            if ($constraint instanceof Predicate) {
                $this->narrow($fetchRequest, $constraint);
            }
            return $fetchRequest;
        }
    }

    private function narrow(FetchRequest $fetchRequest, Predicate $predicate): void
    {
        $fetchRequest->predicate = $fetchRequest->predicate ? CompoundPredicate::andPredicateWithSubpredicates(new ArrayClass([$fetchRequest->predicate, $predicate])) : $predicate;
    }

    public Response $deniedResponse {
        /**
         * @throws Exception
         */
        get {
            $body = $this->fetchRequest->resultType === FetchRequestResultType::countResultType ? new Dictionary([ServiceResponseCountKey => 0]) : new ArrayClass();
            return new Response($this->request->url, body: $body);
        }
    }
    #[Override]
    public Response $response {
        /**
         * @throws Exception
         */
        get {
            $context = $this->managedObjectContext;
            $entityClassName = $this->entity->managedObjectClassName ?? $this->entity->name;
            if ($this->resourceReadConstraint($entityClassName) === false) {
                return $this->deniedResponse;
            }
            $fetchRequest = $this->fetchRequest;
            $fetchRequestResult = match ($fetchRequest->resultType) {
                FetchRequestResultType::managedObjectResultType, FetchRequestResultType::managedObjectIDResultType, FetchRequestResultType::dictionaryResultType => $context->fetch($fetchRequest),
                FetchRequestResultType::countResultType => new Dictionary([ServiceResponseCountKey => $context->count($fetchRequest)])
            };
            $isSecurityEnabled = $this->isSecurityEnabled;
            $transform = fn(ManagedObject|ManagedObjectID|Dictionary $item): ManagedObjectID|Dictionary => $item instanceof ManagedObject ? $this->applySecureRead($item, $item->jsonSerialize()) : $item;
            if ($fetchRequest->fetchBatchSize && match ($fetchRequest->resultType) {
                    FetchRequestResultType::managedObjectResultType, FetchRequestResultType::managedObjectIDResultType => true,
                    default => false
                }) {
                return new StreamResponse($this->request->url, $fetchRequestResult, chunkSize: $fetchRequest->fetchBatchSize, transform: $isSecurityEnabled ? $transform : null);
            }
            if ($isSecurityEnabled && $fetchRequest->resultType === FetchRequestResultType::managedObjectResultType) {
                return new Response($this->request->url, body: $fetchRequestResult->map($transform));
            }
            return new Response($this->request->url, body: $fetchRequestResult);
        }
    }
}
