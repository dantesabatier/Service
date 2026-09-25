<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLResourceKey;
use Sabatier\Foundation\UUID;
use Sabatier\Service\Application;
use Sabatier\Service\BadRequestException;
use Sabatier\Service\DownloadDisposition;
use Sabatier\Service\FileTransferPolicy;
use Sabatier\Service\UploadDisposition;
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

    /**
     * @return list<array{name: string, tmp_name: string, size: int, error: int}>
     * @throws ReflectionException
     */
    private function uploadedFiles(): array
    {
        $enumerator = new UploadsEnumerator("uploads");
        /** @var iterable<array{name: string, tmp_name: string, size: int, error: int}> $files */
        $files = new ReflectionMethod($enumerator, "uploadedFiles")->invoke($enumerator);
        return iterator_to_array($files, false);
    }

    /**
     * A single-file field: every member is a scalar.
     * @throws ReflectionException
     */
    #[Test]
    public function aSingleFileFieldYieldsOneEntry(): void
    {
        $_FILES = ["document" => ["name" => "report.pdf", "tmp_name" => "/tmp/php1", "size" => 2048, "error" => UPLOAD_ERR_OK]];

        $this->assertSame([["name" => "report.pdf", "tmp_name" => "/tmp/php1", "size" => 2048, "error" => UPLOAD_ERR_OK]], $this->uploadedFiles());
    }

    /**
     * A `files[]` field: each member is a parallel array, and the entries have to be rejoined by index.
     * @throws ReflectionException
     */
    #[Test]
    public function aMultipleFieldYieldsOneEntryPerFile(): void
    {
        $_FILES = ["files" => ["name" => ["a.pdf", "b.png"], "tmp_name" => ["/tmp/php1", "/tmp/php2"], "size" => [10, 20], "error" => [UPLOAD_ERR_OK, UPLOAD_ERR_OK]]];

        $this->assertSame([["name" => "a.pdf", "tmp_name" => "/tmp/php1", "size" => 10, "error" => UPLOAD_ERR_OK], ["name" => "b.png", "tmp_name" => "/tmp/php2", "size" => 20, "error" => UPLOAD_ERR_OK]], $this->uploadedFiles());
    }

    /**
     * Several fields in one request, each of either shape.
     * @throws ReflectionException
     */
    #[Test]
    public function bothShapesInOneRequestAreFlattenedTogether(): void
    {
        $_FILES = [
            "cover" => ["name" => "cover.png", "tmp_name" => "/tmp/php0", "size" => 5, "error" => UPLOAD_ERR_OK],
            "files" => ["name" => ["a.pdf", "b.pdf"], "tmp_name" => ["/tmp/php1", "/tmp/php2"], "size" => [10, 20], "error" => [UPLOAD_ERR_OK, UPLOAD_ERR_OK]],
        ];

        $this->assertSame(["cover.png", "a.pdf", "b.pdf"], array_column($this->uploadedFiles(), "name"));
    }

    /**
     * A member array shorter than the names it accompanies yields an empty path and a zero size.
     *
     * The entry is still produced rather than skipped, because the policy is what refuses an upload:
     * dropping it here would silently accept a request that named a file and never stored it.
     * @throws ReflectionException
     */
    #[Test]
    public function aMisalignedMemberFallsBackInsteadOfSkippingTheEntry(): void
    {
        $_FILES = ["files" => ["name" => ["a.pdf", "b.pdf"], "tmp_name" => ["/tmp/php1"], "size" => [10], "error" => [UPLOAD_ERR_OK]]];

        $this->assertSame([["name" => "a.pdf", "tmp_name" => "/tmp/php1", "size" => 10, "error" => UPLOAD_ERR_OK], ["name" => "b.pdf", "tmp_name" => "", "size" => 0, "error" => UPLOAD_ERR_OK]], $this->uploadedFiles());
    }

    /**
     * A field with no `error` member is read as successful, so a hand-built fixture is not mistaken for a failed upload.
     * @throws ReflectionException
     */
    #[Test]
    public function anAbsentErrorMemberReadsAsSuccess(): void
    {
        $_FILES = ["document" => ["name" => "report.pdf", "tmp_name" => "/tmp/php1", "size" => 2048]];

        $this->assertSame(UPLOAD_ERR_OK, $this->uploadedFiles()[0]["error"]);
    }

    /**
     * The transport's own failure is carried through per file, so one rejected file in a multiple upload is distinguishable from its siblings.
     * @throws ReflectionException
     */
    #[Test]
    public function aTransportFailureIsCarriedPerFile(): void
    {
        $_FILES = ["files" => ["name" => ["a.pdf", "b.pdf"], "tmp_name" => ["/tmp/php1", ""], "size" => [10, 0], "error" => [UPLOAD_ERR_OK, UPLOAD_ERR_INI_SIZE]]];

        $this->assertSame([UPLOAD_ERR_OK, UPLOAD_ERR_INI_SIZE], array_column($this->uploadedFiles(), "error"));
    }

    /**
     * Every `UPLOAD_ERR_*` other than OK is answered, and the two size failures are told apart.
     *
     * A file rejected for exceeding `upload_max_filesize` arrives with a size of zero and an empty
     * temporary path: it passes a size check and then fails to move, which used to surface as a `500`
     * where the caller deserved to be told what it did wrong.
     * @throws ReflectionException
     */
    #[Test]
    public function everyTransportFailureHasItsOwnAnswer(): void
    {
        $enumerator = new UploadsEnumerator("uploads");
        $failure = new ReflectionMethod($enumerator, "transportFailure");

        $this->assertNull($failure->invoke($enumerator, UPLOAD_ERR_OK));
        $this->assertStringContainsString("this server accepts", (string)$failure->invoke($enumerator, UPLOAD_ERR_INI_SIZE));
        $this->assertStringContainsString("the form allowed", (string)$failure->invoke($enumerator, UPLOAD_ERR_FORM_SIZE));
        $this->assertStringContainsString("incomplete", (string)$failure->invoke($enumerator, UPLOAD_ERR_PARTIAL));
        $this->assertStringContainsString("No file", (string)$failure->invoke($enumerator, UPLOAD_ERR_NO_FILE));
        foreach ([UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION] as $error) {
            $this->assertNotNull($failure->invoke($enumerator, $error), "A server-side failure must still be answered.");
        }
    }

    /**
     * No upload in the request yields nothing at all.
     * @throws ReflectionException
     */
    #[Test]
    public function anEmptyFilesArrayYieldsNothing(): void
    {
        $_FILES = [];

        $this->assertSame([], $this->uploadedFiles());
    }

    #[Test]
    public function aTransportFailureStopsTheUploadBeforeThePolicyIsConsulted(): void
    {
        $_FILES = ["file" => ["name" => "report.pdf", "tmp_name" => "", "size" => 0, "error" => UPLOAD_ERR_INI_SIZE]];
        $policy = $this->policyAllowing(null);
        try {
            iterator_to_array(new UploadsEnumerator("uploads"));
            $this->fail("A transport failure must stop the upload.");
        } catch (BadRequestException $exception) {
            $this->assertStringContainsString("larger than this server accepts", (string)$exception->error->localizedFailureReason);
        }
        $this->assertSame(0, $policy->evaluations);
    }

    #[Test]
    public function aPolicyThatRefusesTheUploadStopsIt(): void
    {
        $_FILES = ["file" => ["name" => "report.exe", "tmp_name" => "C:/tmp/php1", "size" => 10, "error" => UPLOAD_ERR_OK]];
        $this->policyAllowing(null, "Executables are not accepted.");
        try {
            iterator_to_array(new UploadsEnumerator("uploads"));
            $this->fail("A refused upload must stop.");
        } catch (BadRequestException $exception) {
            $this->assertSame("Executables are not accepted.", $exception->error->localizedFailureReason);
        }
    }

    #[Test]
    public function aRefusalWithoutAReasonStillReadsAsOne(): void
    {
        $_FILES = ["file" => ["name" => "report.exe", "tmp_name" => "C:/tmp/php1", "size" => 10, "error" => UPLOAD_ERR_OK]];
        $this->policyAllowing(null);
        try {
            iterator_to_array(new UploadsEnumerator("uploads"));
            $this->fail("A refused upload must stop.");
        } catch (BadRequestException $exception) {
            $this->assertStringContainsString("was not accepted", (string)$exception->error->localizedFailureReason);
        }
    }

    #[Test]
    public function aRefusalIsHonouredEvenWhenThePolicyStillNamesADestination(): void
    {
        // isAllowed is what decides, not whether a destination happens to be filled in.
        $_FILES = ["file" => ["name" => "report.exe", "tmp_name" => "C:/tmp/php1", "size" => 10, "error" => UPLOAD_ERR_OK]];
        $this->policyRefusingWithDestination(FileManager::default()->temporaryDirectory->appendingPathComponent(new UUID()->uuidString));
        try {
            iterator_to_array(new UploadsEnumerator("uploads"));
            $this->fail("A refused upload must stop even with a destination set.");
        } catch (BadRequestException $exception) {
            $this->assertStringContainsString("was not accepted", (string)$exception->error->localizedFailureReason);
        }
    }

    private function policyRefusingWithDestination(URL $destinationURL): void
    {
        $policy = new class ($destinationURL) implements FileTransferPolicy {
            public function __construct(private readonly URL $destination)
            {
            }

            #[Override]
            public function evaluateUpload(string $directory, string $filename, int $size): UploadDisposition
            {
                return new UploadDisposition(false, $this->destination);
            }

            #[Override]
            public function evaluateDownload(string $directory, string $filename): DownloadDisposition
            {
                return new DownloadDisposition(false);
            }

            #[Override]
            public function directoryURL(string $directory): ?URL
            {
                return FileManager::default()->temporaryDirectory;
            }
        };
        new ReflectionProperty(Application::class, "fileTransferPolicy")->setRawValue(Application::shared(), $policy);
    }

    #[Test]
    public function aFileThatDidNotArriveAsAnUploadIsRefused(): void
    {
        $url = FileManager::default()->temporaryDirectory->appendingPathComponent(new UUID()->uuidString);
        $_FILES = ["file" => ["name" => "report.pdf", "tmp_name" => "C:/tmp/php1", "size" => 10, "error" => UPLOAD_ERR_OK]];
        $this->policyAllowing($url);
        try {
            iterator_to_array(new UploadsEnumerator("uploads"));
            $this->fail("A file that never arrived as an upload must be refused.");
        } catch (BadRequestException $exception) {
            $this->assertStringContainsString("not received as an upload", (string)$exception->error->localizedFailureReason);
        }
    }

    /** Installs a policy on the shared application and hands it back so the test can count its calls. */
    private function policyAllowing(?URL $destinationURL, ?string $failureReason = null): FileTransferPolicy
    {
        $policy = new class ($destinationURL, $failureReason) implements FileTransferPolicy {
            public int $evaluations = 0;

            public function __construct(private readonly ?URL $destination, private readonly ?string $reason)
            {
            }

            #[Override]
            public function evaluateUpload(string $directory, string $filename, int $size): UploadDisposition
            {
                $this->evaluations++;
                return new UploadDisposition($this->destination !== null, $this->destination, failureReason: $this->reason);
            }

            #[Override]
            public function evaluateDownload(string $directory, string $filename): DownloadDisposition
            {
                return new DownloadDisposition(false);
            }

            #[Override]
            public function directoryURL(string $directory): ?URL
            {
                return FileManager::default()->temporaryDirectory;
            }
        };
        new ReflectionProperty(Application::class, "fileTransferPolicy")->setRawValue(Application::shared(), $policy);
        return $policy;
    }

    #[Test]
    public function countsTheFieldsTheRequestCarries(): void
    {
        $_FILES = [];
        $this->assertSame(0, new UploadsEnumerator("uploads")->count);
        $_FILES = ["one" => [], "two" => []];
        $this->assertSame(2, new UploadsEnumerator("uploads")->count);
    }

    #[Test]
    public function itIsEmptyExactlyWhenNoFieldWasSent(): void
    {
        $_FILES = [];
        $this->assertTrue(new UploadsEnumerator("uploads")->isEmpty);
        $_FILES = ["one" => []];
        $this->assertFalse(new UploadsEnumerator("uploads")->isEmpty);
    }

    #[Test]
    public function carriesTheSubdirectoryAndKeysItWasBuiltWith(): void
    {
        $keys = new Set([URLResourceKey::nameKey]);
        $enumerator = new UploadsEnumerator("invoices", $keys);
        $this->assertSame("invoices", $enumerator->directory);
        $this->assertSame($keys, $enumerator->keys);
    }

    #[Test]
    public function theKeysAreOptional(): void
    {
        $this->assertNull(new UploadsEnumerator("uploads")->keys);
    }

    #[Test]
    public function uploadsAreAFlatEnumerationInPreOrder(): void
    {
        $enumerator = new UploadsEnumerator("uploads");
        $this->assertSame(0, $enumerator->level);
        $this->assertFalse($enumerator->isEnumeratingDirectoryPostOrder);
    }

    #[Test]
    public function thereAreNoFileAttributesBeforeAnythingHasBeenWritten(): void
    {
        $this->assertNull(new UploadsEnumerator("uploads")->fileAttributes);
    }

    /** @throws Exception */
    #[Test]
    public function theAttributesOfTheFileLastWrittenAreReported(): void
    {
        $url = FileManager::default()->temporaryDirectory->appendingPathComponent(new UUID()->uuidString);
        FileManager::default()->createFile($url->path, "payload");
        $enumerator = new UploadsEnumerator("uploads");
        new ReflectionProperty(UploadsEnumerator::class, "currentURL")->setValue($enumerator, $url);
        $this->assertSame(7, $enumerator->fileAttributes[FileAttributeKey::size]);
        FileManager::default()->removeItem($url);
    }

    #[Test]
    public function aFileThatIsGoneReportsNoAttributes(): void
    {
        $enumerator = new UploadsEnumerator("uploads");
        new ReflectionProperty(UploadsEnumerator::class, "currentURL")->setValue($enumerator, FileManager::default()->temporaryDirectory->appendingPathComponent(new UUID()->uuidString));
        $this->assertNull($enumerator->fileAttributes);
    }
}
