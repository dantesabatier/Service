<?php

namespace Sabatier\Service;

use Closure;
use Generator;
use IteratorAggregate;
use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectID;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;

/**
 * @implements IteratorAggregate<int, non-empty-string>
 * @internal
 */
final class StreamResponse extends Response implements IteratorAggregate
{
    private int $chunkSize;
    /** @var Closure(ManagedObject|ManagedObjectID): mixed|null */
    private ?Closure $transform;

    /**
     * @param URL $url
     * @param ArrayClass<ManagedObject>|ArrayClass<ManagedObjectID> $body
     * @param int $chunkSize
     * @param Closure(ManagedObject|ManagedObjectID): mixed|null $transform
     */
    public function __construct(URL $url, ArrayClass $body, int $chunkSize, ?Closure $transform)
    {
        parent::__construct($url, headerFields: new Dictionary(["Content-Type" => "application/x-ndjson; charset=utf-8", "Transfer-Encoding" => "chunked", "Cache-Control" => "no-cache"]), body: $body);
        $this->chunkSize = $chunkSize;
        $this->transform = $transform;
        $this->emitter = new StreamEmitter();
    }

    /**
     * @return Generator<int, string, mixed, void>
     */
    #[Override]
    public function getIterator(): Generator
    {
        return (function () {
            /** @var ArrayClass<Dictionary>|ArrayClass<int> $chunk */
            $chunk = new ArrayClass();
            /** @var ArrayClass<ManagedObject>|ArrayClass<ManagedObjectID> $body */
            $body = $this->body;
            foreach ($body as $item) {
                $chunk->append($this->transform ? ($this->transform)($item) : $item);
                if ($chunk->count >= $this->chunkSize) {
                    /** @var non-empty-string $json */
                    $json = json_encode($chunk, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
                    yield "$json";
                    /** @var ArrayClass<Dictionary>|ArrayClass<int> $chunk */
                    $chunk = new ArrayClass();
                }
            }
            if (!$chunk->isEmpty) {
                /** @var non-empty-string $json */
                $json = json_encode($chunk, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
                yield $json . "\n";
            }
        })();
    }
}
