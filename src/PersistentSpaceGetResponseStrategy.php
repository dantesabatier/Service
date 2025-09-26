<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Sabatier\CoreData\BatchFaultingArray;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\Foundation\Dictionary;

/** @internal */
class PersistentSpaceGetResponseStrategy extends PersistentSpaceResponseStrategy
{
    public FetchRequest $fetchRequest {
        get {
            /** @var PersistentSpace $responder */
            $responder = $this->responder;
            return new RequestToFetchRequestAdapter($responder->request, $responder->entity)->fetchRequest;
        }
    }
    public Response $response {
        get {
            $responder = $this->responder;
            $context = $responder->managedObjectContext;
            $fetchRequest = $this->fetchRequest;
            $fetchRequestResult = match ($fetchRequest->resultType) {
                FetchRequestResultType::managedObjectResultType, FetchRequestResultType::managedObjectIDResultType,
                FetchRequestResultType::dictionaryResultType => $context->fetch($fetchRequest),
                FetchRequestResultType::countResultType => new Dictionary(["count" => $context->count($fetchRequest)])
            };
            if ($fetchRequestResult instanceof BatchFaultingArray) {
                return new BatchResponse($responder, $fetchRequest, $fetchRequestResult);
            }
            $responder->content = json_encode($fetchRequestResult, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
            $responder->headerFields["Content-Type"] = "application/json";
            return new Response($responder);
        }
    }
}
