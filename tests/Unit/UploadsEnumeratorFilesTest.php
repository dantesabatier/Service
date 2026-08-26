<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Sabatier\Service\UploadsEnumerator;

/**
 * Fixes how `$_FILES` is flattened into one entry per uploaded file.
 *
 * PHP gives a single-file field scalar members and a `name="files[]"` field parallel arrays, and the
 * two are indistinguishable from the field name alone. Reading `name` without telling them apart
 * works only for the first shape, so a multiple upload used to fail on an array where a string was
 * expected. These cases are that distinction; the enumerator's own writing is not exercised here,
 * since it moves real files.
 */
final class UploadsEnumeratorFilesTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $files = [];

    protected function setUp(): void
    {
        $this->files = $_FILES;
    }

    protected function tearDown(): void
    {
        $_FILES = $this->files;
    }

    /** @return list<array{name: string, tmp_name: string, size: int}> */
    private function uploadedFiles(): array
    {
        $enumerator = new UploadsEnumerator("uploads");
        /** @var iterable<array{name: string, tmp_name: string, size: int}> $files */
        $files = new ReflectionMethod($enumerator, "uploadedFiles")->invoke($enumerator);
        return iterator_to_array($files, false);
    }

    /** A single-file field: every member is a scalar. */
    #[Test]
    public function aSingleFileFieldYieldsOneEntry(): void
    {
        $_FILES = ["document" => ["name" => "report.pdf", "tmp_name" => "/tmp/php1", "size" => 2048]];

        $this->assertSame([["name" => "report.pdf", "tmp_name" => "/tmp/php1", "size" => 2048]], $this->uploadedFiles());
    }

    /** A `files[]` field: each member is a parallel array, and the entries have to be rejoined by index. */
    #[Test]
    public function aMultipleFieldYieldsOneEntryPerFile(): void
    {
        $_FILES = ["files" => ["name" => ["a.pdf", "b.png"], "tmp_name" => ["/tmp/php1", "/tmp/php2"], "size" => [10, 20]]];

        $this->assertSame([["name" => "a.pdf", "tmp_name" => "/tmp/php1", "size" => 10], ["name" => "b.png", "tmp_name" => "/tmp/php2", "size" => 20]], $this->uploadedFiles());
    }

    /** Several fields in one request, each of either shape. */
    #[Test]
    public function bothShapesInOneRequestAreFlattenedTogether(): void
    {
        $_FILES = [
            "cover" => ["name" => "cover.png", "tmp_name" => "/tmp/php0", "size" => 5],
            "files" => ["name" => ["a.pdf", "b.pdf"], "tmp_name" => ["/tmp/php1", "/tmp/php2"], "size" => [10, 20]],
        ];

        $this->assertSame(["cover.png", "a.pdf", "b.pdf"], array_column($this->uploadedFiles(), "name"));
    }

    /**
     * A member array shorter than the names it accompanies yields an empty path and a zero size.
     *
     * The entry is still produced rather than skipped, because the policy is what refuses an upload:
     * dropping it here would silently accept a request that named a file and never stored it.
     */
    #[Test]
    public function aMisalignedMemberFallsBackInsteadOfSkippingTheEntry(): void
    {
        $_FILES = ["files" => ["name" => ["a.pdf", "b.pdf"], "tmp_name" => ["/tmp/php1"], "size" => [10]]];

        $this->assertSame([["name" => "a.pdf", "tmp_name" => "/tmp/php1", "size" => 10], ["name" => "b.pdf", "tmp_name" => "", "size" => 0]], $this->uploadedFiles());
    }

    /** No upload in the request yields nothing at all. */
    #[Test]
    public function anEmptyFilesArrayYieldsNothing(): void
    {
        $_FILES = [];

        $this->assertSame([], $this->uploadedFiles());
    }
}
