<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Closure;
use Generator;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/**
 * Abstract base for GET responders that stream Server-Sent Events.
 *
 * Subclasses declare their own #[Endpoint] and provide an event factory through
 * $events. The hook is read while preparing the response; its closure is invoked
 * only when streaming starts, after the session has been committed. Validate the
 * request and capture any required session data in the hook before returning the
 * closure. Event production and polling belong to the concrete source.
 *
 * @psalm-consistent-constructor
 * @phpstan-consistent-constructor
 */
abstract class EventStreamResponder extends Responder
{
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get]);
    }
    /** @var Closure(): Generator<int, ServerSentEvent> The factory invoked when the response is streamed. */
    abstract protected Closure $events {
        get;
    }
    #[Override]
    public Response $response {
        get {
            try {
                $this->allowedMethods->containsElement($this->request->httpMethod) ?: throw new MethodNotAllowedException();
                if ($this->isSessionEnabled) {
                    $this->session->start();
                }
                $events = $this->events;
                return new ResponsePipeline($this->transformers->union($this->infrastructureTransformers), $this->transformerContext)->process(new EventStreamResponse($this->request->url, $events));
            } finally {
                if ($this->isSessionEnabled) {
                    $this->session->commit();
                }
            }
        }
    }
}
