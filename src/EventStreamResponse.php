<?php

namespace Sabatier\Service;

use Closure;
use Generator;
use IteratorAggregate;
use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;

/**
 * Represents an HTTP response that streams Server-Sent Events (SSE).
 *
 * This response produces a `text/event-stream` body and emits events
 * incrementally as they are generated. Events are provided by a generator
 * function that yields {@see ServerSentEvent} instances.
 *
 * Each yielded event is serialized and formatted according to the
 * Server-Sent Events specification before being written to the response
 * stream.
 *
 * Example:
 *
 * ```php
 * return new EventStreamResponse($url, function () {
 *     while (true) {
 *         yield new ServerSentEvent(["time" => time()]);
 *         sleep(1);
 *     }
 * });
 * ```
 *
 * @implements IteratorAggregate<int, non-empty-string>
 *
 * @see https://html.spec.whatwg.org/multipage/server-sent-events.html
 */
final class EventStreamResponse extends Response implements IteratorAggregate
{
    /** @var Closure(): Generator<int, ServerSentEvent> */
    private Closure $generator;

    /**
     * Creates a new Server-Sent Events response.
     *
     * The provided generator will be executed when the response body is streamed. Each yielded {@see ServerSentEvent} will be formatted and written to the event stream.
     * @param URL $url The URL associated with this response.
     * @param Closure(): Generator<int, ServerSentEvent> $generator A generator that produces events to be sent to the client. The generator may yield indefinitely for long-lived streams.
     */
    public function __construct(URL $url, Closure $generator)
    {
        parent::__construct($url, headerFields: new Dictionary(["Content-Type" => "text/event-stream", "Cache-Control" => "no-cache", "Connection" => "keep-alive", "X-Accel-Buffering" => "no"]));
        $this->generator = $generator;
        $this->emitter = new EventStreamEmitter();
    }

    #[Override]
    public function getIterator(): Generator
    {
        foreach (($this->generator)() as $event) {
            if (connection_aborted()) {
                break;
            }
            if ($event->id !== null) {
                yield "id: $event->id";
            }
            if ($event->event !== null) {
                yield "event: $event->event";
            }
            $data = json_encode($event, JSON_THROW_ON_ERROR);
            foreach (explode("\n", $data) as $line) {
                yield "data: $line";
            }
            yield "";
        }
    }
}
