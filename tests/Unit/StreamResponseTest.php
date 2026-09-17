<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\URL;
use Sabatier\Service\StreamEmitter;
use Sabatier\Service\StreamResponse;

final class StreamResponseTest extends TestCase
{
    #[Test]
    public function announcesItselfAsAnUncachedChunkedNDJSONStream(): void
    {
        $headerFields = $this->response(new ArrayClass([1]), 1)->allHeaderFields;
        $this->assertSame("application/x-ndjson; charset=utf-8", $headerFields["Content-Type"]);
        $this->assertSame("chunked", $headerFields["Transfer-Encoding"]);
        $this->assertSame("no-cache", $headerFields["Cache-Control"]);
    }

    #[Test]
    public function streamsThroughTheStreamEmitter(): void
    {
        $this->assertInstanceOf(StreamEmitter::class, $this->response(new ArrayClass([1]), 1)->emitter);
    }

    #[Test]
    public function emitsOneChunkPerFullBatch(): void
    {
        $this->assertSame(["[1,2]", "[3,4]"], $this->chunks(new ArrayClass([1, 2, 3, 4]), 2));
    }

    #[Test]
    public function theTrailingPartialBatchIsEmittedToo(): void
    {
        $this->assertSame(["[1,2]", "[3,4]", "[5]\n"], $this->chunks(new ArrayClass([1, 2, 3, 4, 5]), 2));
    }

    #[Test]
    public function aBodyShorterThanOneBatchIsASingleChunk(): void
    {
        $this->assertSame(["[1,2]\n"], $this->chunks(new ArrayClass([1, 2]), 5));
    }

    #[Test]
    public function anEmptyBodyEmitsNothingAtAll(): void
    {
        $this->assertSame([], $this->chunks(new ArrayClass(), 2));
    }

    #[Test]
    public function aBatchSizeOfOneEmitsEveryItemSeparately(): void
    {
        $this->assertSame(["[1]", "[2]", "[3]"], $this->chunks(new ArrayClass([1, 2, 3]), 1));
    }

    #[Test]
    public function theTransformIsAppliedToEveryItem(): void
    {
        $this->assertSame(["[2,4]", "[6]\n"], $this->chunks(new ArrayClass([1, 2, 3]), 2, fn(int $item): int => $item * 2));
    }

    #[Test]
    public function withoutATransformTheItemsAreSerializedAsTheyCame(): void
    {
        $this->assertSame(["[\"a\",\"b\"]\n"], $this->chunks(new ArrayClass(["a", "b"]), 4));
    }

    #[Test]
    public function zeroFractionsSurviveSerialization(): void
    {
        $this->assertSame(["[1.0,2.5]\n"], $this->chunks(new ArrayClass([1.0, 2.5]), 4));
    }

    #[Test]
    public function iteratingTwiceReplaysTheWholeStream(): void
    {
        $response = $this->response(new ArrayClass([1, 2, 3]), 2);
        $this->assertSame(iterator_to_array($response->getIterator()), iterator_to_array($response->getIterator()));
    }

    /**
     * @param ArrayClass<mixed> $body
     * @return list<string>
     */
    private function chunks(ArrayClass $body, int $chunkSize, ?callable $transform = null): array
    {
        return iterator_to_array($this->response($body, $chunkSize, $transform)->getIterator(), false);
    }

    /** @param ArrayClass<mixed> $body */
    private function response(ArrayClass $body, int $chunkSize, ?callable $transform = null): StreamResponse
    {
        return new StreamResponse(new URL("http://localhost/orders"), $body, $chunkSize, $transform === null ? null : $transform(...));
    }
}
