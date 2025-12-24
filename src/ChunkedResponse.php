<?php

namespace Sabatier\Service;

use IteratorAggregate;
use JsonSerializable;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;
use Traversable;

/** @internal */
class ChunkedResponse extends Response implements IteratorAggregate
{
    public int $count {
        get => $this->body->count;
    }
    private int $chunkSize;

    public function __construct(URL $url, mixed $body, int $chunkSize)
    {
        parent::__construct($url, headerFields: new Dictionary(["Content-Type" => "application/x-ndjson; charset=utf-8", "Transfer-Encoding" => "chunked"]), body: $body);
        $this->chunkSize = $chunkSize;
        $this->emitter = new ChunkedEmitter();
    }

    /**
     * @return Traversable<string>
     */
    #[Override]
    public function getIterator(): Traversable
    {
        return (function () {
            $chunk = new ArrayClass();
            /** @var ArrayClass<JsonSerializable> $body */
            $body = $this->body;
            foreach ($body as $index => $item) {
                $chunk[] = $item;
                if ($chunk->count >= $this->chunkSize || $index + 1 === $body->count) {
                    yield json_encode($chunk, JSON_PRESERVE_ZERO_FRACTION);
                    $chunk = new ArrayClass();
                }
            }
        })();
    }
}
