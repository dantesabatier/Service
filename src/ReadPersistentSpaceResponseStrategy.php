<?php

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

/** @internal */
final class ReadPersistentSpaceResponseStrategy extends PersistentSpaceResponseStrategy
{
    public FetchRequest $fetchRequest {
        /**
         * @throws Exception
         */
        get {
            $fetchRequest = new RequestToFetchRequestAdapter($this->request, $this->entity)->fetchRequest;
            if ($this->isSecurityEnabled && $this->hasOwnScope && ($ownerKey = OwnerResolver::getOwnerFieldName($this->entity->managedObjectClassName ?? $this->entity->name))) {
                $ownershipPredicate = new ComparisonPredicate(Expression::expressionForKeyPath($ownerKey), Expression::expressionForConstantValue($this->user));
                if ($fetchRequest->predicate) {
                    $fetchRequest->predicate = CompoundPredicate::andPredicateWithSubpredicates(new ArrayClass([$fetchRequest->predicate, $ownershipPredicate]));
                } else {
                    $fetchRequest->predicate = $ownershipPredicate;
                }
            }
            return $fetchRequest;
        }
    }
    #[Override]
    public Response $response {
        /**
         * @throws Exception
         */
        get {
            $context = $this->managedObjectContext;
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
