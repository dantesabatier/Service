<?php

namespace Sabatier\Service;

use IteratorAggregate;
use Override;
use Sabatier\CoreData\FetchRequest;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Traversable;

/**
 * @template-implements IteratorAggregate<int, string>
 */
class BatchResponse extends Response implements IteratorAggregate
{
    public readonly int $count;
    public bool $isEmpty {
        get => $this->count === 0;
    }
    public Dictionary $allHeaderFields {
        get {
            $headerFields = $this->allHeaderFields;
            $headerFields["Content-Type"] = "text/plain; charset=utf-8";
            $headerFields["Transfer-Encoding"] = "chunked";
            return $headerFields;
        }
    }
    private readonly FetchRequest $fetchRequest;
    private readonly ArrayClass $fetchRequestResults;

    public function __construct(Responder $responder, FetchRequest $fetchRequest, ArrayClass $fetchRequestResults)
    {
        parent::__construct($responder);
        $this->fetchRequest = $fetchRequest;
        $this->fetchRequestResults = $fetchRequestResults;
        $this->count = (int)ceil($fetchRequestResults->count / max($fetchRequest->fetchBatchSize, 1));
        $this->emitter = new BatchEmitter();
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return (function () {
            $cursor = 1;
            $records = new ArrayClass();
            foreach ($this->fetchRequestResults as $index => $fetchRequestResult) {
                $records[] = $fetchRequestResult;
                if ((($index + 1) === ($cursor * $this->fetchRequest->fetchBatchSize))) {
                    yield json_encode($records, JSON_PRESERVE_ZERO_FRACTION);
                    $records = new ArrayClass();
                    $cursor++;
                }
            }
        })();
    }
}
