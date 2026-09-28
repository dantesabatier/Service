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
            new DeletePersistentSpaceOperation($this->managedObjectContext, $this->fieldSecurityPolicy, $this->entity, $objectID)->perform();
            return new Response($this->request->url, HTTPStatusCode::noContent);
        }
    }
}
