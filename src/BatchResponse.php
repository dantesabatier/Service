<?php

namespace Sabatier\Service;

use IteratorAggregate;
use Sabatier\CoreData\FetchRequest;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\URL;
use Traversable;

/**
 * @template-implements IteratorAggregate<int, string>
 * @internal
 */
class BatchResponse extends HTTPURLResponse implements IteratorAggregate
{
    public readonly int $count;
    public readonly bool $isEmpty;

    public function __construct(URL $url, private readonly ArrayClass $fetchRequestResults, private readonly FetchRequest $fetchRequest)
    {
        parent::__construct($url, headerFields: new Dictionary(["Content-Type" => "text/plain; charset=utf-8", "Transfer-Encoding" => "chunked"]));
        $this->count = (int)ceil($this->fetchRequestResults->count() / max($this->fetchRequest->fetchBatchSize, 1));
        $this->isEmpty = $this->count === 0;
    }

    public function getIterator(): Traversable
    {
        return (function () {
            $cursor = 1;
            $records = new ArrayClass();
            $fetchRequest = $this->fetchRequest;
            $fetchBatchSize = $fetchRequest->fetchBatchSize;
            foreach ($this->fetchRequestResults as $index => $fetchRequestResult) {
                $records->append($fetchRequestResult);
                if ((($index + 1) === ($cursor * $fetchBatchSize))) {
                    yield json_encode($records, JSON_PRESERVE_ZERO_FRACTION);
                    $records = new ArrayClass();
                    $cursor++;
                }
            }
        })();
    }
}
