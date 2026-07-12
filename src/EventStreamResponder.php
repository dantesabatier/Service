<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Sabatier\CoreData\FetchRequest;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Set;

/** @internal */
#[Endpoint("Events")]
final class EventStreamResponder extends Responder
{
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get]);
    }
    private FetchRequest $fetchRequest {
        get => $this->fetchRequest ??= new RequestToFetchRequestAdapter($this->request, $this->managedObjectContext)->fetchRequest;
    }
    #[Override]
    public Response $response {
        get {
            try {
                $this->allowedMethods->containsElement($this->request->httpMethod) ?: throw new MethodNotAllowedException();
                if ($this->isSessionEnabled) {
                    $this->session->start();
                }
                return new ResponsePipeline(new Set([ResponseHeaderSanitizerTransformer::class, RateLimitHeaderTransformer::class, SecurityHeadersTransformer::class, CORSResponseTransformer::class]), $this->transformerContext)->process(
                    new EventStreamResponse($this->request->url, new EventStream($this->fetchRequest, $this->managedObjectContext)->generator(...))
                );
            } finally {
                if ($this->isSessionEnabled) {
                    $this->session->commit();
                }
            }
        }
    }
}
