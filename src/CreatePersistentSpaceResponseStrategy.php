<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Number;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

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
            $this->assertUniqueness($parsedBody[ManagedObjectObjectIDKey] ?? 0);
            $object = EntityDescription::insertNewObject($entity->name, $context);
            $this->applySecureUpdate($object, $parsedBody);
            $context->save();
            /** @var ManagedObject $refreshed */
            $refreshed = $this->fetchBy($object->objectID);
            $body = $this->applySecureRead($refreshed, $refreshed->jsonSerialize());
            return new Response($request->url, HTTPStatusCode::created, body: $body);
        }
    }

    /**
     * @throws Exception
     */
    private function assertUniqueness(ManagedObjectID|int $objectID): void
    {
        if (!$objectID) {
            return;
        }
        /** @var FetchRequest<Number> $fetchRequest */
        $fetchRequest = $this->fetchRequestFor($objectID);
        if ($this->managedObjectContext->count($fetchRequest)) {
            throw new ConflictException();
        }
    }
}
