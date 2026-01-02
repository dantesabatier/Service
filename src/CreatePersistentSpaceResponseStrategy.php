<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Networking\HTTPStatusCode;

/** @internal */
final class CreatePersistentSpaceResponseStrategy extends PersistentSpaceResponseStrategy
{
    public Response $response {
        /**
         * @throws Exception
         */
        get {
            $request = $this->request;
            $parsedBody = $request->parsedBody;
            $context = $this->managedObjectContext;
            $entity = $this->entity;
            $this->assertUniqueness($parsedBody[ServiceIdentity::identityKey] ?? 0);
            $object = EntityDescription::insertNewObject($entity->name, $context);
            $this->applySecureUpdate($object, $parsedBody);
            $context->save();
            /** @var ManagedObject $refreshed */
            $refreshed = $this->fetchBy($object->objectID);
            $body = $this->applySecureRead($refreshed, $refreshed->jsonSerialize());
            return new Response($request->url, HTTPStatusCode::created, body: $body);
        }
    }
}
