<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Number;
use function Sabatier\Foundation\localized_string;
use const Sabatier\CoreData\ManagedObjectObjectIDKey;

/** @internal */
final class CreatePersistentSpaceResponseStrategy extends PersistentSpaceResponseStrategy
{
    #[Override]
    public Response $response {
        /**
         * @throws Exception
         */
        get {
            $request = $this->request;
            $parameters = $request->parameters;
            $context = $this->managedObjectContext;
            $entity = $this->entity;
            $this->assertUniqueness($parameters[ManagedObjectObjectIDKey] ?? 0);
            $object = EntityDescription::insertNewObject($entity->name, $context);
            $this->applySecureUpdate($object, $parameters);
            $context->save();
            $refreshed = $this->fetchBy($object->objectID) ?? throw new InternalServerErrorException(localized_string("failed to fetch refreshed object after creation"));
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
