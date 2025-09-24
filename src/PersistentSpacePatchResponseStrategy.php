<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Sabatier\CoreData\SQLEntity;

/** @internal */
class PersistentSpacePatchResponseStrategy extends PersistentSpaceResponseStrategy
{
    public Response $response {
        get {
            $responder = $this->responder;
            $request = $responder->request;
            $body = $request->parsedBody;
            if (!($objectID = $body[SQLEntity::primaryKeyName])) {
                throw new BadRequestException(sprintf("\"%s\" can not be null", SQLEntity::primaryKeyName));
            }
            if (!($object = $this->managedObject($objectID))) {
                throw new NotFoundException();
            }
            $context = $responder->managedObjectContext;
            $object->setValuesForKeys($body);
            $context->save();
            $object = $this->managedObject($object->objectID);
            $responder->content = json_encode($object?->serialized($request->serialization), JSON_PRESERVE_ZERO_FRACTION);
            $responder->headerFields["Content-Type"] = "application/json";
            return new Response($responder);
        }
    }
}
