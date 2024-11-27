<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Sabatier\CoreData\SQLEntity;
use Sabatier\Foundation\Networking\HTTPStatusCode;

/** @internal */
class PersistentSpaceRespondentDeleter extends PersistentSpaceRespondent
{
    public Response $response {
        get {
            $responder = $this->responder;
            if (!($objectID = $responder->request->parsedBody[SQLEntity::primaryKeyName])) {
                throw new BadRequestException(sprintf("\"%s\" can not be null", SQLEntity::primaryKeyName));
            }
            if (!($object = $this->managedObject($objectID))) {
                throw new NotFoundException();
            }
            $context = $responder->managedObjectContext;
            $context->delete($object);
            $context->save();
            $responder->statusCode = HTTPStatusCode::noContent;
            return new Response($responder);
        }
    }
}
