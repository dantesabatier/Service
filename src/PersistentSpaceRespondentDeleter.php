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
            $body = $this->body;
            if (!($objectID = $body[SQLEntity::primaryKeyName])) {
                throw new BadRequestException(sprintf("\"%s\" can not be null", SQLEntity::primaryKeyName));
            }
            if (!($object = $this->managedObject($objectID))) {
                throw new NotFoundException();
            }
            $responder = $this->responder;
            $context = $responder->managedObjectContext;
            $context->delete($object);
            $context->save();
            $responder->statusCode = HTTPStatusCode::noContent;
            return new Response($responder);
        }
    }
}
