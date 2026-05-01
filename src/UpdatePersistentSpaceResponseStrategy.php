<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

/** @internal */
final class UpdatePersistentSpaceResponseStrategy extends PersistentSpaceResponseStrategy
{
    #[Override]
    public Response $response {
        /**
         * @throws Exception
         */
        get {
            $request = $this->request;
            $parameters = $request->parameters;
            $objectID = $parameters[ManagedObjectObjectIDKey] ?? throw new BadRequestException();
            $object = $this->fetchBy($objectID) ?? throw new NotFoundException();
            $this->enforceOwnership($object);
            $this->applySecureUpdate($object, $parameters);
            $context = $this->managedObjectContext;
            if ($context->hasChanges) {
                $context->save();
            }
            $refreshed = $this->fetchBy($object->objectID) ?? throw new InternalServerErrorException();
            $body = $this->applySecureRead($refreshed, $refreshed->jsonSerialize());
            return new Response($request->url, body: $body);
        }
    }
}
