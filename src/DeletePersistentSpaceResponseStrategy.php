<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

/** @internal */
final class DeletePersistentSpaceResponseStrategy extends PersistentSpaceResponseStrategy
{
    public Response $response {
        /**
         * @throws Exception
         */
        get {
            if (!($objectID = $this->request->parsedBody[ManagedObjectObjectIDKey])) {
                throw new BadRequestException();
            }
            if (!($object = $this->fetchBy($objectID))) {
                throw new NotFoundException();
            }
            $this->enforceOwnership($object);
            $context = $this->managedObjectContext;
            $context->delete($object);
            $context->save();
            return new Response($this->request->url, HTTPStatusCode::noContent);
        }
    }
}
