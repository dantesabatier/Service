<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\URL;
use Sabatier\Service\Emitter;
use Sabatier\Service\Response;
use Sabatier\Service\StreamEmitter;

final class EmitterTest extends TestCase
{
    private int $bufferLevel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bufferLevel = ob_get_level();
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->bufferLevel) {
            ob_end_clean();
        }
        parent::tearDown();
    }

    #[Test]
    public function writesTheContentOutUncompressed(): void
    {
        $this->assertSame("hello", $this->capture(new Emitter(), "hello"));
    }

    #[Test]
    public function writesAnEmptyBodyWithoutOutput(): void
    {
        $this->assertSame("", $this->capture(new Emitter(), ""));
    }

    #[Test]
    public function reusesAnAlreadyOpenBufferRatherThanNestingAnother(): void
    {
        ob_start();
        ob_start();
        $level = ob_get_level();
        $this->emitContent(new Emitter(), "hello", false);
        $this->assertSame($level, ob_get_level());
        ob_end_clean();
        ob_end_clean();
    }

    #[Test]
    public function compressionIsOnlyConsideredWhenNoBufferIsOpenSoTheContentIsWrittenEitherWay(): void
    {
        $this->assertSame("hello", $this->captureCompressed(new Emitter(), "hello"));
    }

    #[Test]
    public function flushesItsOwnBufferOutToTheEnclosingOne(): void
    {
        ob_start();
        ob_start();
        $this->emitContent(new Emitter(), "hello", false);
        $inner = (string)ob_get_clean();
        $outer = (string)ob_get_clean();
        $this->assertSame("", $inner);
        $this->assertSame("hello", $outer);
    }

    #[Test]
    public function emittingHeadersLeavesTheResponseIntact(): void
    {
        $response = $this->response();
        new ReflectionMethod(Emitter::class, "emitHeaders")->invoke(new Emitter(), $response, new Dictionary(["X-Trace" => "abc"]), 12);
        $this->assertSame(HTTPStatusCode::ok, $response->statusCode);
    }

    #[Test]
    public function emittingHeadersWithoutAContentLengthIsAccepted(): void
    {
        new ReflectionMethod(Emitter::class, "emitHeaders")->invoke(new Emitter(), $this->response(), new Dictionary(["X-Trace" => "abc"]));
        $this->assertFalse(headers_sent());
    }

    #[Test]
    public function theStreamEmitterWritesEachChunkOnItsOwnLine(): void
    {
        $this->assertSame("first\nsecond\nthird\n", $this->capture(new StreamEmitter(), ["first", "second", "third"]));
    }

    #[Test]
    public function theStreamEmitterWritesNothingForAnEmptySequence(): void
    {
        $this->assertSame("", $this->capture(new StreamEmitter(), []));
    }

    #[Test]
    public function theStreamEmitterStringifiesEachChunk(): void
    {
        $this->assertSame("1\n2\n", $this->capture(new StreamEmitter(), [1, 2]));
    }

    private function response(): Response
    {
        return new Response(new URL("http://localhost/orders"), HTTPStatusCode::ok, new Dictionary(), null);
    }

    /**
     * Emitter::emitContent ends in ob_flush, which pushes its buffer out to the enclosing one, while
     * StreamEmitter::emitContent only flushes and leaves its output in place. Capturing at two levels
     * and concatenating them reads the output wherever the emitter happened to leave it.
     */
    private function capture(Emitter $emitter, mixed $content): string
    {
        return $this->captureWith($emitter, $content, false);
    }

    private function captureCompressed(Emitter $emitter, mixed $content): string
    {
        return $this->captureWith($emitter, $content, true);
    }

    private function captureWith(Emitter $emitter, mixed $content, bool $useCompression): string
    {
        ob_start();
        ob_start();
        $this->emitContent($emitter, $content, $useCompression);
        $inner = (string)ob_get_clean();
        return (string)ob_get_clean() . $inner;
    }

    private function emitContent(Emitter $emitter, mixed $content, bool $useCompression): void
    {
        new ReflectionMethod($emitter::class, "emitContent")->invoke($emitter, $content, $useCompression);
    }
}
