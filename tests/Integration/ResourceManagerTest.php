<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UUID;
use Sabatier\Service\ContentTypeTransformer;
use Sabatier\Service\NoCacheHeaderTransformer;
use Sabatier\Service\NotFoundException;
use Sabatier\Service\ResourceManager;
use Sabatier\Service\StaticCacheHeaderTransformer;
use Sabatier\Service\StaticResourceDisposition;

final class ResourceManagerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $server;
    /** @var ArrayClass<URL> */
    private ArrayClass $writtenURLs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = $_SERVER;
        $this->writtenURLs = new ArrayClass();
        ObjectClass::$staticAssociatedValues = [];
    }

    /** @throws Exception */
    protected function tearDown(): void
    {
        $this->writtenURLs->forEach(fn(URL $url) => FileManager::default()->removeItem($url));
        $_SERVER = $this->server;
        ObjectClass::$staticAssociatedValues = [];
        parent::tearDown();
    }

    #[Test]
    public function servesOnlyHeadAndGet(): void
    {
        $this->assertSame([HTTPRequestMethod::head, HTTPRequestMethod::get], $this->allowedMethods($this->manager("/style.css")));
    }

    #[Test]
    public function negotiatesOnlyTheContentTypeHeader(): void
    {
        /** @var ArrayClass<string> $allowedHeaders */
        $allowedHeaders = new ReflectionProperty(ResourceManager::class, "allowedHeaders")->getValue($this->manager("/style.css"));
        $this->assertSame(["Content-Type"], $allowedHeaders->array);
    }

    #[Test]
    public function resolvesTheResourceUnderTheDocumentRoot(): void
    {
        $resourceURL = $this->resourceURL($this->manager("/assets/style.css"));
        $this->assertStringStartsWith(FileManager::default()->documentRootDirectory->path, $resourceURL->path);
        $this->assertStringEndsWith("/assets/style.css", $resourceURL->path);
    }

    #[Test]
    public function theResolvedResourceIsMemoized(): void
    {
        $manager = $this->manager("/style.css");
        $this->assertSame($this->resourceURL($manager), $this->resourceURL($manager));
    }

    #[Test]
    public function itAnswersOnlyForADispositionThatClaimsTheRequest(): void
    {
        $this->assertTrue($this->manager("/style.css", $this->disposition(shouldHandle: true))->isFirstResponder);
        $this->assertFalse($this->manager("/style.css", $this->disposition(shouldHandle: false))->isFirstResponder);
    }

    #[Test]
    public function theDispositionDecidesWhetherProtectedContentIsAvailable(): void
    {
        $this->assertTrue($this->manager("/style.css", $this->disposition(isProtectedContentAvailable: true))->isProtectedContentAvailable);
        $this->assertFalse($this->manager("/style.css", $this->disposition(isProtectedContentAvailable: false))->isProtectedContentAvailable);
    }

    #[Test]
    public function aCacheableResourceIsServedWithTheStaticCacheHeaders(): void
    {
        $transformers = $this->transformers($this->manager("/style.css", $this->disposition(cacheable: true)));
        $this->assertTrue($transformers->containsElement(ContentTypeTransformer::class));
        $this->assertTrue($transformers->containsElement(StaticCacheHeaderTransformer::class));
        $this->assertFalse($transformers->containsElement(NoCacheHeaderTransformer::class));
    }

    #[Test]
    public function anUncacheableResourceIsServedWithTheNoCacheHeaders(): void
    {
        $transformers = $this->transformers($this->manager("/style.css", $this->disposition(cacheable: false)));
        $this->assertTrue($transformers->containsElement(NoCacheHeaderTransformer::class));
        $this->assertFalse($transformers->containsElement(StaticCacheHeaderTransformer::class));
    }

    #[Test]
    public function theTransformerChainIsBuiltOnce(): void
    {
        $manager = $this->manager("/style.css", $this->disposition(cacheable: true));
        $this->assertSame($this->transformers($manager), $this->transformers($manager));
    }

    #[Test]
    public function aHeadRequestCarriesNoBody(): void
    {
        $this->assertNull($this->data($this->manager("/style.css", method: HTTPRequestMethod::head)));
    }

    /** @throws Exception */
    #[Test]
    public function aReadableFileIsServedAsItsContents(): void
    {
        $name = $this->writeResource("body { color: red }");
        $this->assertSame("body { color: red }", $this->data($this->manager("/$name")));
    }

    #[Test]
    public function anAbsentFileIsNotFoundWhenTheDispositionForbidsAnEmptyBody(): void
    {
        $this->expectException(NotFoundException::class);
        $this->data($this->manager("/" . new UUID()->uuidString . ".css", $this->disposition(allowEmptyResponse: false)));
    }

    #[Test]
    public function anAbsentFileYieldsAnEmptyBodyWhenTheDispositionAllowsIt(): void
    {
        $this->assertNull($this->data($this->manager("/" . new UUID()->uuidString . ".css", $this->disposition(allowEmptyResponse: true))));
    }

    /** @throws Exception */
    #[Test]
    public function anAssignedBodyIsServedInPlaceOfTheFile(): void
    {
        $name = $this->writeResource("on disk");
        $manager = $this->manager("/$name");
        // The resolved flag is raised by the setter, so assigning is what pins the body against a later read.
        new ReflectionProperty(ResourceManager::class, "data")->setValue($manager, "assigned");
        $this->assertSame("assigned", $this->data($manager));
    }

    /** @throws Exception */
    private function writeResource(string $contents): string
    {
        $name = new UUID()->uuidString . ".css";
        $url = FileManager::default()->documentRootDirectory->appendingPathComponent($name);
        FileManager::default()->createFile($url->path, $contents);
        $this->writtenURLs->append($url);
        return $name;
    }

    private function disposition(bool $shouldHandle = true, bool $allowEmptyResponse = false, bool $cacheable = false, bool $isProtectedContentAvailable = true): StaticResourceDisposition
    {
        return new StaticResourceDisposition($shouldHandle, false, $allowEmptyResponse, $cacheable, $isProtectedContentAvailable);
    }

    /** @return list<string> */
    private function allowedMethods(ResourceManager $manager): array
    {
        /** @var list<string> */
        return new ReflectionProperty(ResourceManager::class, "allowedMethods")->getValue($manager)->array;
    }

    private function resourceURL(ResourceManager $manager): URL
    {
        /** @var URL */
        return new ReflectionProperty(ResourceManager::class, "resourceURL")->getValue($manager);
    }

    /** @return Set<string> */
    private function transformers(ResourceManager $manager): Set
    {
        /** @var Set<string> */
        return new ReflectionProperty(ResourceManager::class, "transformers")->getValue($manager);
    }

    private function data(ResourceManager $manager): mixed
    {
        return new ReflectionProperty(ResourceManager::class, "data")->getValue($manager);
    }

    private function manager(string $path, ?StaticResourceDisposition $disposition = null, string $method = HTTPRequestMethod::get): ResourceManager
    {
        ObjectClass::$staticAssociatedValues = [];
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["REQUEST_URI"] = $path;
        $_SERVER["REQUEST_METHOD"] = $method;
        $manager = new ResourceManager();
        if ($disposition) {
            new ReflectionProperty(ResourceManager::class, "staticResourceDisposition")->setRawValue($manager, $disposition);
        }
        return $manager;
    }
}
