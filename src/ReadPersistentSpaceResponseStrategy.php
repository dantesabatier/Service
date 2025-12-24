<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\BatchFaultingArray;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
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
                FetchRequestResultType::managedObjectResultType, FetchRequestResultType::managedObjectIDResultType,
                FetchRequestResultType::dictionaryResultType => $context->fetch($fetchRequest),
                FetchRequestResultType::countResultType => new Dictionary(["count" => $context->count($fetchRequest)])
            };
            if ($fetchRequestResult instanceof BatchFaultingArray) {
                return new ChunkedResponse($this->request->url, $fetchRequestResult, $fetchRequest->fetchBatchSize);
            }
            return new Response($this->request->url, body: $fetchRequestResult);
        }
    }
}
