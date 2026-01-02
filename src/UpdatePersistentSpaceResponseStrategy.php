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
                throw new BadRequestException();
            }
            if (!($object = $this->fetchBy($objectID))) {
                throw new NotFoundException();
            }
            $this->enforceOwnership($object);
            $this->applySecureUpdate($object, $parsedBody);
            $this->managedObjectContext->save();
            /** @var ManagedObject $refreshed */
            $refreshed = $this->fetchBy($object->objectID);
            $body = $this->applySecureRead($refreshed, $refreshed->jsonSerialize());
            return new Response($request->url, body: $body);
        }
    }
}
