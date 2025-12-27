<?php

namespace Sabatier\Service;

use Generator;
use IteratorAggregate;
use JsonSerializable;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;

/**
 * @implements IteratorAggregate<int, non-empty-string>
 * @internal
 */
final class ChunkedResponse extends Response implements IteratorAggregate
{
    private int $chunkSize;

    public function __construct(URL $url, mixed $body, int $chunkSize)
    {
        parent::__construct($url, headerFields: new Dictionary(["Content-Type" => "application/x-ndjson; charset=utf-8", "Transfer-Encoding" => "chunked", "Cache-Control" => "no-cache"]), body: $body);
        $this->chunkSize = $chunkSize;
        $this->emitter = new ChunkedEmitter();
    }

    /**
     * @return Generator<int, non-empty-string, mixed, void>
     */
    #[Override]
    public function getIterator(): Generator
    {
        return (function () {
            /** @var ArrayClass<JsonSerializable> $chunk */
            $chunk = new ArrayClass();
            /** @var ArrayClass<JsonSerializable> $body */
            $body = $this->body;
            foreach ($body as $index => $item) {
                $chunk[] = $item;
                if ($chunk->count >= $this->chunkSize || $index + 1 === $body->count) {
                    /** @var non-empty-string $json */
                    $json = json_encode($chunk, JSON_PRESERVE_ZERO_FRACTION);
                    yield $json;
                    $chunk = new ArrayClass();
                }
            }
        })();
    }
}
