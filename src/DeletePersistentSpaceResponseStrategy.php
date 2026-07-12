<?php

declare(strict_types=1);

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
            $objectID = $this->request->parameters[ManagedObjectObjectIDKey] ?? throw new BadRequestException();
            $object = $this->fetchBy($objectID) ?? throw new NotFoundException();
            $this->enforceOwnership($object);
            $this->enforceResourceAccess($object);
            $context = $this->managedObjectContext;
            $context->delete($object);
            $context->save();
            return new Response($this->request->url, HTTPStatusCode::noContent);
        }
    }
}
