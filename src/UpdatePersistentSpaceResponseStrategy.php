<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Exception;

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
            if (!($objectID = $body[ServiceIdentity::identityKey])) {
                throw new BadRequestException(sprintf("\"%s\" can not be null", ServiceIdentity::identityKey));
            }
            if (!($object = $this->managedObject($objectID))) {
                throw new NotFoundException();
            }
            $this->verify($object);
            $object->setValuesForKeys(new FieldSecurityFilter($object, $this->authorizationContext->user)->filterWrite($body));
            $this->managedObjectContext->save();
            $object = $this->managedObject($object->objectID);
            return new Response($request->url, body: $object?->serialized($request->serialization));
        }
    }
}
