<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\URL;

/**
 * Fixes the decomposition `Downloader::download()` performs on the `url` it is given.
 *
 * The responder reads superglobals through `Request`, so driving the whole action from a test would
 * mean standing up an `Application`. What matters here is narrower and is what the four lines of the
 * action actually do: turn one URL into a directory name and a filename, and refuse anything whose
 * shape is not one directory and one file. Those two names are all that reaches
 * {@see \Sabatier\Service\FileTransferPolicy}, and {@see DefaultFileTransferPolicyTest} covers what
 * it does with them.
 */
final class DownloaderRequestShapeTest extends TestCase
{
    /**
     * The lines under test, mirrored from `Downloader::download()`.
     *
     * @return array{string, string}|null The directory and filename the policy would be asked about, or `null` where the responder raises `BadRequestException` first.
     */
    private function transferComponents(string $urlString): ?array
    {
        $attachmentURL = new URL($urlString);
        $directoryURL = $attachmentURL->deletingLastPathComponent();
        return $directoryURL->pathComponents->count === 2 ? [$directoryURL->lastPathComponent, $attachmentURL->lastPathComponent] : null;
    }

    /** The documented request shape: a full URL whose path names one directory and one file. */
    #[Test]
    public function aFullURLOneDirectoryDeepYieldsTheTwoNames(): void
    {
        $this->assertSame(["uploads", "report.pdf"], $this->transferComponents("https://api.example.com/uploads/report.pdf"));
    }

    /** @return list<array{string}> */
    public static function traversingLocations(): array
    {
        return [["https://api.example.com/../etc/passwd"], ["https://api.example.com/uploads/../../etc/passwd"], ["https://api.example.com/uploads/../etc/passwd"], ["https://api.example.com/%2e%2e/etc/passwd"]];
    }

    /**
     * A path carrying a dot component is refused on its shape alone, before the policy is consulted.
     *
     * Nothing is canonicalized here, which is what makes the count decisive: `..` stays a component,
     * so a traversing path is deeper than one directory and one file and never reaches the policy.
     */
    #[Test]
    #[DataProvider("traversingLocations")]
    public function aTraversingLocationIsRefusedOnItsShape(string $urlString): void
    {
        $this->assertNull($this->transferComponents($urlString), "\"$urlString\" must not resolve to a directory and a filename.");
    }

    /** @return list<array{string}> */
    public static function locationsOfTheWrongDepth(): array
    {
        return [["https://api.example.com/report.pdf"], ["https://api.example.com/uploads/documents/report.pdf"], ["https://api.example.com/"], ["https://api.example.com/a/b/c/d.txt"]];
    }

    /**
     * Any other depth is refused rather than reinterpreted.
     *
     * One level is what `Uploader` writes to, so there is nothing legitimate to read from two. Guessing
     * which component was meant to be the directory would serve a file the request did not ask for.
     */
    #[Test]
    #[DataProvider("locationsOfTheWrongDepth")]
    public function aLocationOfAnotherDepthIsRefused(string $urlString): void
    {
        $this->assertNull($this->transferComponents($urlString), "\"$urlString\" is not one directory and one file.");
    }

    /** Only the path is read, so the host is immaterial — the file is resolved against this server's document root either way. */
    #[Test]
    public function theHostIsIgnored(): void
    {
        $this->assertSame($this->transferComponents("https://api.example.com/uploads/report.pdf"), $this->transferComponents("http://somewhere.else.invalid/uploads/report.pdf"));
    }

    /** A query or a fragment belongs to the URL, not to the name of the file, and must not end up in either component. */
    #[Test]
    public function aQueryOrFragmentDoesNotReachTheFilename(): void
    {
        $this->assertSame(["uploads", "report.pdf"], $this->transferComponents("https://api.example.com/uploads/report.pdf?v=2"));
        $this->assertSame(["uploads", "report.pdf"], $this->transferComponents("https://api.example.com/uploads/report.pdf#page=1"));
    }
}
