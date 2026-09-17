<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\URL;
use Sabatier\Service\BadRequestException;
use Sabatier\Service\Downloader;

/**
 * Drives the responder's own hooks, rather than a copy of their logic, so the shape a download is
 * refused on is fixed against the code that enforces it.
 */
final class DownloaderTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = $_SERVER;
        ObjectClass::$staticAssociatedValues = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        ObjectClass::$staticAssociatedValues = [];
        parent::tearDown();
    }

    #[Test]
    public function onlyPostIsAllowed(): void
    {
        $this->assertSame([HTTPRequestMethod::post], $this->allowedMethods($this->downloader("https://api.example.com/uploads/report.pdf")));
    }

    #[Test]
    public function readsTheDownloadLocationFromTheRequest(): void
    {
        $this->assertSame("https://api.example.com/uploads/report.pdf", $this->downloadURL($this->downloader("https://api.example.com/uploads/report.pdf"))->absoluteString);
    }

    #[Test]
    public function theDownloadLocationIsResolvedOnlyOnce(): void
    {
        $downloader = $this->downloader("https://api.example.com/uploads/report.pdf");
        $this->assertSame($this->downloadURL($downloader), $this->downloadURL($downloader));
    }

    #[Test]
    public function aRequestNamingNoLocationIsRefused(): void
    {
        $this->expectException(BadRequestException::class);
        $this->downloadURL($this->downloader(null));
    }

    #[Test]
    public function aLocationOneDirectoryDeepYieldsThatDirectory(): void
    {
        $this->assertSame("uploads", $this->directoryURL($this->downloader("https://api.example.com/uploads/report.pdf"))->lastPathComponent);
    }

    #[Test]
    public function theDirectoryIsResolvedOnlyOnce(): void
    {
        $downloader = $this->downloader("https://api.example.com/uploads/report.pdf");
        $this->assertSame($this->directoryURL($downloader), $this->directoryURL($downloader));
    }

    /** @return iterable<string, array{string}> */
    public static function traversingLocationProvider(): iterable
    {
        yield "a parent reference at the root" => ["https://api.example.com/../etc/passwd"];
        yield "two parent references" => ["https://api.example.com/uploads/../../etc/passwd"];
        yield "one parent reference" => ["https://api.example.com/uploads/../etc/passwd"];
        yield "a percent-encoded parent reference" => ["https://api.example.com/%2e%2e/etc/passwd"];
    }

    #[Test]
    #[DataProvider("traversingLocationProvider")]
    public function aTraversingLocationIsRefusedOnItsShape(string $urlString): void
    {
        $this->expectException(BadRequestException::class);
        $this->directoryURL($this->downloader($urlString));
    }

    /** @return iterable<string, array{string}> */
    public static function misshapenLocationProvider(): iterable
    {
        yield "a file at the root" => ["https://api.example.com/report.pdf"];
        yield "a file two directories deep" => ["https://api.example.com/uploads/2026/report.pdf"];
        yield "no path at all" => ["https://api.example.com"];
    }

    #[Test]
    #[DataProvider("misshapenLocationProvider")]
    public function aLocationThatIsNotOneDirectoryAndOneFileIsRefused(string $urlString): void
    {
        $this->expectException(BadRequestException::class);
        $this->directoryURL($this->downloader($urlString));
    }

    /** @return list<string> */
    private function allowedMethods(Downloader $downloader): array
    {
        /** @var list<string> */
        return new ReflectionProperty(Downloader::class, "allowedMethods")->getValue($downloader)->array;
    }

    private function downloadURL(Downloader $downloader): URL
    {
        /** @var URL */
        return new ReflectionProperty(Downloader::class, "downloadURL")->getValue($downloader);
    }

    private function directoryURL(Downloader $downloader): URL
    {
        /** @var URL */
        return new ReflectionProperty(Downloader::class, "directoryURL")->getValue($downloader);
    }

    private function downloader(?string $urlString): Downloader
    {
        ObjectClass::$staticAssociatedValues = [];
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["REQUEST_URI"] = $urlString === null ? "/Downloader" : "/Downloader?url=" . rawurlencode($urlString);
        $_SERVER["REQUEST_METHOD"] = HTTPRequestMethod::post;
        return new Downloader();
    }
}
