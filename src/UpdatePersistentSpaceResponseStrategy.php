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
            $body = new UpdatePersistentSpaceOperation($this->managedObjectContext, $this->fieldSecurityPolicy, $this->entity, $objectID, $parameters, $request->serialization)->perform();
            return new Response($request->url, body: $body);
        }
    }
}
