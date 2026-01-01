<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObject;

/** @internal */
final class UpdatePersistentSpaceResponseStrategy extends PersistentSpaceResponseStrategy
{
    public Response $response {
        /**
         * @throws Exception
         */
        get {
            $request = $this->request;
            $parsedBody = $request->parsedBody;
            if (!($objectID = $parsedBody[ServiceIdentity::identityKey])) {
                throw new BadRequestException(sprintf("\"%s\" can not be null", ServiceIdentity::identityKey));
            }
            if (!($object = $this->fetchBy($objectID))) {
                throw new NotFoundException();
            }
            $this->enforceOwnership($object);
            $this->applySecureUpdate($object, $parsedBody);
            $this->managedObjectContext->save();
            /** @var ManagedObject $refreshed */
            $refreshed = $this->fetchBy($object->objectID);
            $serialized = $refreshed->serialized($request->serialization);
            $body = $this->applySecureRead($serialized, $serialized->dictionaryWithValues($serialized->serializationKeys));
            return new Response($request->url, body: $body);
        }
    }
}
