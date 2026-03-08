<?php

namespace Sabatier\Service;

use Closure;
use Generator;
use IteratorAggregate;
use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;

/**
 * @implements IteratorAggregate<int, non-empty-string>
 */
final class EventStreamResponse extends Response implements IteratorAggregate
{
    /** @var Closure(): Generator<int, ServerSentEvent> */
    private Closure $generator;

    /**
     * @param URL $url
     * @param Closure(): Generator<int, ServerSentEvent> $generator
     */
    public function __construct(URL $url, Closure $generator)
    {
        parent::__construct($url, headerFields: new Dictionary(["Content-Type" => "text/event-stream", "Cache-Control" => "no-cache", "Connection" => "keep-alive", "X-Accel-Buffering" => "no"]));
        $this->generator = $generator;
        $this->emitter = new StreamEmitter();
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
