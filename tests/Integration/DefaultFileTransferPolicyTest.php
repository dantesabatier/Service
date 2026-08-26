<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\URL;
use Sabatier\Service\DefaultFileTransferPolicy;
use const Sabatier\Service\FileTransferAllowedExtensionsKey;
use const Sabatier\Service\FileTransferDirectoriesKey;
use const Sabatier\Service\FileTransferFilePermissionsDefault;
use const Sabatier\Service\FileTransferMaximumSizeKey;

/**
 * Exercises DefaultFileTransferPolicy against the real filesystem.
 *
 * The cases that matter are the ones asserting a name cannot describe anywhere but the
 * directory it is stored in. The read side has a gate of its own — StaticResourcePolicy
 * refuses any path carrying a dot component, `..` included — but a write never passes
 * through it, so the filename has to be settled by this policy.
 */
final class DefaultFileTransferPolicyTest extends TestCase
{
    private DefaultFileTransferPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new DefaultFileTransferPolicy();
    }

    /** @return list<array{string}> */
    public static function traversingNames(): array
    {
        return [["../afuera.txt"], ["../../etc/passwd"], ["sub/../../afuera.txt"], ["dir/file.txt"], ["..\\afuera.txt"], [".htaccess"], ["."], [".."], ["a..b"], ["invoice . pdf"], [".hidden.txt"], ["a.b.c"]];
    }

    #[Test]
    #[DataProvider("traversingNames")]
    public function aFilenameThatCouldDescribeAnotherLocationIsRefused(string $filename): void
    {
        $disposition = $this->policy->evaluateUpload("uploads", $filename, 10);

        $this->assertFalse($disposition->isAllowed, "\"$filename\" must not be accepted as an upload name.");
        $this->assertNull($disposition->destinationURL);
    }

    /** Rejecting beats taking the basename: an adjusted name stores a file the request never asked for. */
    #[Test]
    public function aRefusedNameIsNotQuietlyReducedToItsBasename(): void
    {
        $disposition = $this->policy->evaluateUpload("uploads", "../../passwd", 10);

        $this->assertFalse($disposition->isAllowed);
        $this->assertNull($disposition->filename);
    }

    #[Test]
    #[DataProvider("traversingNames")]
    public function aDownloadNameThatCouldDescribeAnotherLocationIsRefused(string $filename): void
    {
        $disposition = $this->policy->evaluateDownload("uploads", $filename);

        $this->assertFalse($disposition->isAllowed, "\"$filename\" must not be accepted as a download name.");
        $this->assertNull($disposition->resourceURL);
    }

    #[Test]
    public function aDirectoryThatIsNotASinglePlainComponentIsRefused(): void
    {
        foreach (["../etc", "a/b", "", ".", ".."] as $directory) {
            $this->assertNull($this->policy->directoryURL($directory), "\"$directory\" must not resolve to a directory.");
            $this->assertFalse($this->policy->evaluateUpload($directory, "file.txt", 10)->isAllowed);
        }
    }

    #[Test]
    public function anAcceptedUploadLandsUnderTheResolvedDirectory(): void
    {
        $disposition = $this->policy->evaluateUpload("uploads", "invoice.pdf", 2048);

        $this->assertTrue($disposition->isAllowed);
        $this->assertSame("invoice.pdf", $disposition->filename);
        $expected = FileManager::default()->documentRootDirectory->appendingPathComponent("uploads")->appendingPathComponent("invoice.pdf");
        $this->assertSame($expected->path, $disposition->destinationURL?->path);
    }

    /**
     * The decomposition Downloader performs on the `url` it has always accepted.
     *
     * Kept in step with the responder: it splits the requested location with the URL it already
     * built, and a dot component survives that split as a name the policy then refuses — which is
     * why the old request shape can stay without reopening the traversal it used to allow. An empty
     * pair stands for a location the responder rejects before the policy is consulted.
     *
     * @return array{string, string}
     */
    private function transferComponents(string $urlString): array
    {
        $attachmentURL = new URL("file://$urlString");
        $directoryURL = $attachmentURL->deletingLastPathComponent();
        return $directoryURL->pathComponents->count === 2 ? [$directoryURL->lastPathComponent, $attachmentURL->lastPathComponent] : ["", ""];
    }

    /** The endpoint keeps the request shape it always had, so an application already calling it does not have to change. */
    #[Test]
    public function aLocationOneDirectoryDeepStillResolves(): void
    {
        [$directory, $filename] = $this->transferComponents("/uploads/invoice.pdf");

        $disposition = $this->policy->evaluateDownload($directory, $filename);

        $this->assertSame("uploads", $directory);
        $this->assertSame("invoice.pdf", $filename);
        $this->assertFalse($disposition->isAllowed, "The file does not exist, so the static policy refuses it — but the components resolved.");
    }

    /** Accepting the old `url` shape must not reopen the traversal it used to allow: the dot component survives the split and the policy refuses it. */
    #[Test]
    #[DataProvider("traversingLocations")]
    public function aTraversingLocationIsRefusedThroughTheOldRequestShape(string $urlString): void
    {
        [$directory, $filename] = $this->transferComponents($urlString);

        $this->assertFalse($this->policy->evaluateDownload($directory, $filename)->isAllowed, "\"$urlString\" must not resolve to a readable file.");
    }

    /** @return list<array{string}> */
    public static function traversingLocations(): array
    {
        return [["/../afuera.txt"], ["/%2e%2e/afuera.txt"], ["/a/b/c.txt"], ["/x.pdf"], ["/./afuera.txt"]];
    }

    /** The extension is read with `URL::$pathExtension` rather than by splitting the name here, so it agrees with what the rest of the framework calls an extension — `ContentTypeTransformer` picks the served type the same way. */
    #[Test]
    public function theExtensionIsReadTheWayTheFrameworkReadsIt(): void
    {
        $environment = ProcessInfo::processInfo()->environment;
        $environment[FileTransferAllowedExtensionsKey] = "pdf,png";
        try {
            $policy = new DefaultFileTransferPolicy();
            $this->assertTrue($policy->evaluateUpload("uploads", "invoice.pdf", 10)->isAllowed);
            $this->assertTrue($policy->evaluateUpload("uploads", "invoice.PDF", 10)->isAllowed, "The comparison is case-insensitive.");
            $this->assertFalse($policy->evaluateUpload("uploads", "invoice.html", 10)->isAllowed);
            $this->assertFalse($policy->evaluateUpload("uploads", "invoice", 10)->isAllowed, "A name with no extension cannot match an allowlist.");
        } finally {
            unset($environment[FileTransferAllowedExtensionsKey]);
        }
    }

    /** With FILE_TRANSFER_DIRECTORIES set, a subdirectory outside the list is refused however plain its name is — the shape check and the allowlist are separate gates. */
    #[Test]
    public function aSubdirectoryOutsideTheConfiguredListIsRefused(): void
    {
        $environment = ProcessInfo::processInfo()->environment;
        $environment[FileTransferDirectoriesKey] = "uploads,invoices";
        try {
            $policy = new DefaultFileTransferPolicy();
            $this->assertNotNull($policy->directoryURL("uploads"));
            $this->assertNotNull($policy->directoryURL("invoices"));
            $this->assertNull($policy->directoryURL("secrets"), "A plain name that is not on the list must still be refused.");
            $this->assertFalse($policy->evaluateUpload("secrets", "invoice.pdf", 10)->isAllowed);
            $this->assertFalse($policy->evaluateDownload("secrets", "invoice.pdf")->isAllowed);
        } finally {
            unset($environment[FileTransferDirectoriesKey]);
        }
    }

    /** Unset, the list accepts any name the shape check passes — the framework does not presume which subdirectories an application has. */
    #[Test]
    public function anUnsetDirectoryListAcceptsAnyPlainName(): void
    {
        $this->assertNotNull($this->policy->directoryURL("anything"));
        $this->assertNotNull($this->policy->directoryURL("something-else_2"));
    }

    /** The ceiling is compared against the size the transport reported, and a file exactly at it is still accepted. */
    #[Test]
    public function anUploadLargerThanTheConfiguredMaximumIsRefused(): void
    {
        $environment = ProcessInfo::processInfo()->environment;
        $environment[FileTransferMaximumSizeKey] = "1024";
        try {
            $policy = new DefaultFileTransferPolicy();
            $this->assertTrue($policy->evaluateUpload("uploads", "invoice.pdf", 1024)->isAllowed, "A file exactly at the maximum is within it.");
            $this->assertFalse($policy->evaluateUpload("uploads", "invoice.pdf", 1025)->isAllowed);
            $this->assertStringContainsString("1024", (string)$policy->evaluateUpload("uploads", "invoice.pdf", 4096)->failureReason);
        } finally {
            unset($environment[FileTransferMaximumSizeKey]);
        }
    }

    /** Unset, the framework imposes no ceiling of its own, leaving the transport's `upload_max_filesize` as the only one. */
    #[Test]
    public function anUnsetMaximumImposesNoCeiling(): void
    {
        $this->assertTrue($this->policy->evaluateUpload("uploads", "invoice.pdf", PHP_INT_MAX)->isAllowed);
    }

    /** A deployment whose writing and reading processes differ cannot read back a stricter file, so the default stays permissive rather than breaking the feature it protects. */
    #[Test]
    public function theDefaultFilePermissionsArePreserved(): void
    {
        $disposition = $this->policy->evaluateUpload("uploads", "invoice.pdf", 1);

        $this->assertSame(FileTransferFilePermissionsDefault, $disposition->fileAttributes?->offsetGet(FileAttributeKey::posixPermissions));
    }
}
