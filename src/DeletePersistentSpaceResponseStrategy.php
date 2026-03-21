<?php

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

/** @internal */
final class DeletePersistentSpaceResponseStrategy extends PersistentSpaceResponseStrategy
{
    #[Override]
    public Response $response {
        /**
         * @throws Exception
         */
        get {
            if (!($objectID = $this->request->parameters[ManagedObjectObjectIDKey])) {
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
