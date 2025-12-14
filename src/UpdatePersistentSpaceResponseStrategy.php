<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\SQLEntity;

/** @internal */
final class UpdatePersistentSpaceResponseStrategy extends PersistentSpaceResponseStrategy
{
    public Response $response {
        /**
         * @throws Exception
         */
        get {
            $request = $this->request;
            $body = $request->parsedBody;
            if (!($objectID = $body[SQLEntity::primaryKeyName])) {
                throw new BadRequestException(sprintf("\"%s\" can not be null", SQLEntity::primaryKeyName));
            }
            if (!($object = $this->managedObject($objectID))) {
                throw new NotFoundException();
            }
            $context = $this->managedObjectContext;
            $object->setValuesForKeys($body);
            $context->save();
            $object = $this->managedObject($object->objectID);
            return new Response($request->url, body: $object?->serialized($request->serialization));
        }
    }
}
