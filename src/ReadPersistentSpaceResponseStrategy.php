<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\CoreData\ManagedObject;
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
        get => $this->executeSecureFetch($this->fetchRequest);
    }

    /**
     * @throws Exception
     */
    private function executeSecureFetch(FetchRequest $fetchRequest): Response
    {
        $context = $this->managedObjectContext;
        $fetchRequestResult = match ($fetchRequest->resultType) {
            FetchRequestResultType::managedObjectResultType,
            FetchRequestResultType::managedObjectIDResultType,
            FetchRequestResultType::dictionaryResultType => $context->fetch($fetchRequest),
            FetchRequestResultType::countResultType => new Dictionary([ServiceResponseCountKey => $context->count($fetchRequest)])
        };
        if ($fetchRequest->fetchBatchSize) {
            return new StreamResponse($this->request->url, $fetchRequestResult, chunkSize: $fetchRequest->fetchBatchSize, transform: fn(ManagedObject $object): Dictionary => $this->applySecureRead($object, $object->jsonSerialize()));
        }
        if ($fetchRequest->resultType === FetchRequestResultType::managedObjectResultType) {
            return new Response($this->request->url, body: $fetchRequestResult->map(fn(ManagedObject $object): Dictionary => new FieldSecurityFilter($object, $this->authorizationContext->user)->filterRead($object->jsonSerialize())));
        }
        return new Response($this->request->url, body: $fetchRequestResult);
    }
}
