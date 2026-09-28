<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\Foundation\Networking\HTTPStatusCode;

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
            $body = new CreatePersistentSpaceOperation($this->managedObjectContext, $this->fieldSecurityPolicy, $this->entity, $request->parameters, $request->serialization)->perform();
            return new Response($request->url, HTTPStatusCode::created, body: $body);
        }
    }
}
