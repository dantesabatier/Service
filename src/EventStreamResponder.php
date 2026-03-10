<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Sabatier\Service;

use Override;
use Sabatier\CoreData\FetchRequest;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/** @internal */
#[Endpoint("Events")]
final class EventStreamResponder extends Responder
{
    /** @var ArrayClass<string> */
    #[Override]
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::get]);
    }
    public bool $isProtectedContentAvailable = true;
    private FetchRequest $fetchRequest {
        get => $this->fetchRequest ??= new RequestToFetchRequestAdapter($this->request, $this->managedObjectContext)->fetchRequest;
    }
    #[Override]
    public Response $response {
        get => new CORSResponseDecorator(new ResponseHeaderSanitizerDecorator(new EventStreamResponse($this->request->url, new EventStream($this->fetchRequest, $this->managedObjectContext)->generator(...)))->response, $this->request, $this->corsPolicy)->response;
    }
}
