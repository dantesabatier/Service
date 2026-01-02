<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\Foundation\Dictionary;

/** @internal */
final class ReadPersistentSpaceResponseStrategy extends PersistentSpaceResponseStrategy
{
    public FetchRequest $fetchRequest {
        get {
            return new RequestToFetchRequestAdapter($this->request, $this->entity)->fetchRequest;
        }
    }
    public Response $response {
        /**
         * @throws Exception
         */
        get {
            $context = $this->managedObjectContext;
            $fetchRequest = $this->fetchRequest;
            $fetchRequestResult = match ($fetchRequest->resultType) {
                FetchRequestResultType::managedObjectResultType,
                FetchRequestResultType::managedObjectIDResultType,
                FetchRequestResultType::dictionaryResultType => $context->fetch($fetchRequest),
                FetchRequestResultType::countResultType => new Dictionary([ServiceResponseCountKey => $context->count($fetchRequest)])
            };
            $transform = fn(ManagedObject|ManagedObjectID|Dictionary $item): ManagedObject|ManagedObjectID|Dictionary => $item instanceof ManagedObject ? $this->applySecureRead($item, $item->jsonSerialize()) : $item;
            if ($fetchRequest->fetchBatchSize) {
                return new StreamResponse($this->request->url, $fetchRequestResult, chunkSize: $fetchRequest->fetchBatchSize, transform: $transform);
            }
            if ($fetchRequest->resultType === FetchRequestResultType::managedObjectResultType) {
                return new Response($this->request->url, body: $fetchRequestResult->map($transform));
            }
            return new Response($this->request->url, body: $fetchRequestResult);
        }
    }
}
