<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\SQLEntity;
use Sabatier\Foundation\Networking\HTTPStatusCode;

/** @internal */
final class DeletePersistentSpaceResponseStrategy extends PersistentSpaceResponseStrategy
{
    public Response $response {
        /**
         * @throws Exception
         */
        get {
            if (!($objectID = $this->request->parsedBody[SQLEntity::primaryKeyName])) {
                throw new BadRequestException(sprintf("\"%s\" can not be null", SQLEntity::primaryKeyName));
            }
            if (!($object = $this->managedObject($objectID))) {
                throw new NotFoundException();
            }
            $context = $this->managedObjectContext;
            $context->delete($object);
            $context->save();
            return new Response($this->request->url, HTTPStatusCode::noContent);
        }
    }
}
