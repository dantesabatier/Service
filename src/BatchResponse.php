<?php

namespace Sabatier\Service;

use Generator;
use IteratorAggregate;
use Override;
use Sabatier\CoreData\FetchRequest;
use Sabatier\Foundation\ArrayClass;

/**
 * @template-implements IteratorAggregate<int, string>
 * @internal
 */
class BatchResponse extends Response implements IteratorAggregate
{
    private(set) int $count;
    public bool $isEmpty {
        get => $this->count === 0;
    }
    private ArrayClass $fetchRequestResults;
    private int $fetchBatchSize;

    public function __construct(Responder $responder, FetchRequest $fetchRequest, ArrayClass $fetchRequestResults)
    {
        parent::__construct($responder);
        $allHeaderFields = $this->allHeaderFields;
        $allHeaderFields["Content-Type"] = "text/plain; charset=utf-8";
        $allHeaderFields["Transfer-Encoding"] = "chunked";
        $this->fetchRequestResults = $fetchRequestResults;
        $this->fetchBatchSize = min($fetchRequest->fetchBatchSize, $fetchRequestResults->count);
        $this->count = (int)ceil($fetchRequestResults->count / max($this->fetchBatchSize, 1));
        $this->emitter = new BatchEmitter();
    }

    #[Override]
    public function getIterator(): Generator
    {
        return (function () {
            $cursor = 1;
            $records = new ArrayClass();
            foreach ($this->fetchRequestResults as $index => $fetchRequestResult) {
                $records[] = $fetchRequestResult;
                if ((($index + 1) === ($cursor * $this->fetchBatchSize))) {
                    yield json_encode($records, JSON_PRESERVE_ZERO_FRACTION);
                    $records = new ArrayClass();
                    $cursor++;
                }
            }
        })();
    }
}
