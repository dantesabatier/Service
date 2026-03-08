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
 * @psalm-type SSEEmitter Closure(string, string|null=, string|null=): string
 * @psalm-type SSEGenerator = Closure(SSEEmitter): Generator<int,string>
 */
final class EventStreamResponse extends Response implements IteratorAggregate
{
    /** @var SSEGenerator */
    private Closure $generator;

    /**
     * @param URL $url
     * @param SSEGenerator $generator
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
        $emit = function (string $data, ?string $event = null, ?string $id = null): string {
            $buffer = "";
            if ($id !== null) {
                $buffer .= "id: $id\n";
            }
            if ($event !== null) {
                $buffer .= "event: $event\n";
            }
            foreach (explode("\n", $data) as $line) {
                $buffer .= "data: $line\n";
            }
            return "$buffer\n";
        };
        return ($this->generator)($emit);
    }
}
