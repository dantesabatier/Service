<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Sabatier\CoreData\FetchRequest;

/** @internal */
final class ReadPersistentSpaceResponseStrategy extends PersistentSpaceResponseStrategy
{
    public FetchRequest $fetchRequest {
        get {
            return new RequestToFetchRequestAdapter($this->request, $this->entity)->fetchRequest;
        }
    }
    public Response $response {
        get => new Response($this->request->url, body: $this->executeSecureFetch($this->fetchRequest));
    }
}
