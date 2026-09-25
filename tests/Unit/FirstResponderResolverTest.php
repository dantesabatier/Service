<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UUID;
use Sabatier\Service\Application;
use Sabatier\Service\FirstResponderResolver;
use Sabatier\Service\Responder;

abstract class ChainLinkResponderFixture extends Responder
{
}

final class HeadChainLinkResponderFixture extends ChainLinkResponderFixture
{
}

final class TailChainLinkResponderFixture extends ChainLinkResponderFixture
{
}

final class MiddleChainLinkResponderFixture extends ChainLinkResponderFixture
{
}

final class FirstResponderResolverTest extends TestCase
{
    private ?URL $scratchURL = null;

    /** @throws Exception */
    protected function tearDown(): void
    {
        if ($this->scratchURL) {
            FileManager::default()->removeItem($this->scratchURL);
            $this->scratchURL = null;
        }
        parent::tearDown();
    }

    /** @throws ReflectionException */
    #[Test]
    public function buildsAClassNameFromTheNamespaceTheDirectoryAndTheFileStem(): void
    {
        $className = $this->invoke("buildClassName", "App", URL::fileURL("C:/app/src/Responders"), URL::fileURL("C:/app/src/Responders/OrdersResponder.php"));
        $this->assertSame("App\\Responders\\OrdersResponder", $className);
    }

    /** @throws ReflectionException */
    #[Test]
    public function acceptsAnInstantiableResponderSubclass(): void
    {
        $this->assertTrue($this->invoke("isValidResponderClass", HeadChainLinkResponderFixture::class));
    }

    /** @throws ReflectionException */
    #[Test]
    public function rejectsAClassThatDoesNotExist(): void
    {
        $this->assertFalse($this->invoke("isValidResponderClass", "App\\Responders\\Nope"));
    }

    /** @throws ReflectionException */
    #[Test]
    public function rejectsAClassThatIsNotAResponder(): void
    {
        $this->assertFalse($this->invoke("isValidResponderClass", self::class));
    }

    /** @throws ReflectionException */
    #[Test]
    public function rejectsResponderItself(): void
    {
        $this->assertFalse($this->invoke("isValidResponderClass", Responder::class));
    }

    /** @throws ReflectionException */
    #[Test]
    public function rejectsAnAbstractResponderSubclass(): void
    {
        $this->assertFalse($this->invoke("isValidResponderClass", ChainLinkResponderFixture::class));
    }

    /** @throws Exception */
    #[Test]
    public function keepsOnlyPhpFilesRegardlessOfTheExtensionCase(): void
    {
        $directoryURL = $this->scratchDirectory(["OrdersResponder.php", "UsersResponder.PHP", "README.md", "notes.txt"]);
        $names = $this->invoke("filteredFileURLs", $directoryURL)->map(fn(URL $url): string => $url->lastPathComponent)->array;
        sort($names);
        $this->assertSame(["OrdersResponder.php", "UsersResponder.PHP"], $names);
    }

    /** @throws ReflectionException */
    #[Test]
    public function returnsNullWhenTheDiscoveryDirectoryIsAbsent(): void
    {
        $this->assertNull($this->invoke("responderClasses", "App", URL::fileURL("C:/definitely/not/here")));
    }

    /** @throws Exception */
    #[Test]
    public function keepsOnlyTheFilesThatResolveToAnInstantiableResponder(): void
    {
        $directoryURL = $this->scratchDirectory(["HeadChainLinkResponderFixture.php", "ChainLinkResponderFixture.php", "GhostResponderFixture.php"], "Unit");
        $classes = $this->invoke("responderClasses", "Sabatier\\Service\\Tests", $directoryURL);
        $this->assertSame([HeadChainLinkResponderFixture::class], $classes?->array);
    }

    /** @throws ReflectionException */
    #[Test]
    public function linksEveryResponderIntoASingleChainAndReturnsItsHead(): void
    {
        $head = new HeadChainLinkResponderFixture();
        $middle = new MiddleChainLinkResponderFixture();
        $tail = new TailChainLinkResponderFixture();
        $this->assertSame($head, $this->invoke("buildResponderChain", new ArrayClass([$head, $middle, $tail])));
        $this->assertSame($middle, $head->nextResponder);
        $this->assertSame($tail, $middle->nextResponder);
        $this->assertNull($tail->nextResponder);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aSingleResponderChainLeavesItsTailOpen(): void
    {
        $only = new HeadChainLinkResponderFixture();
        $this->assertSame($only, $this->invoke("buildResponderChain", new ArrayClass([$only])));
        $this->assertNull($only->nextResponder);
    }

    /** @throws ReflectionException */
    #[Test]
    public function mergingOntoAnAbsentChainYieldsTheSecondChainUntouched(): void
    {
        $second = new TailChainLinkResponderFixture();
        $this->assertSame($second, $this->invoke("mergeResponderChains", null, $second));
        $this->assertNull($second->nextResponder);
    }

    /** @throws ReflectionException */
    #[Test]
    public function mergingAppendsTheSecondChainToTheTailOfTheFirst(): void
    {
        $head = new HeadChainLinkResponderFixture();
        $middle = new MiddleChainLinkResponderFixture();
        $head->nextResponder = $middle;
        $tail = new TailChainLinkResponderFixture();
        $this->assertSame($head, $this->invoke("mergeResponderChains", $head, $tail));
        $this->assertSame($middle, $head->nextResponder);
        $this->assertSame($tail, $middle->nextResponder);
    }

    /** @throws ReflectionException */
    #[Test]
    public function mergingNothingOntoAChainLeavesItTerminated(): void
    {
        $head = new HeadChainLinkResponderFixture();
        $this->assertSame($head, $this->invoke("mergeResponderChains", $head, null));
        $this->assertNull($head->nextResponder);
    }

    /** @throws ReflectionException */
    #[Test]
    public function discoversNothingWhenTheBundleHasNoResponderDirectories(): void
    {
        $this->assertTrue(new ArrayClass($this->invokeOnWiredResolver("discoverResponderClasses", "App"))->isEmpty);
    }

    /** @throws ReflectionException */
    #[Test]
    public function thereIsNoCustomResponderWithoutADelegate(): void
    {
        $this->assertNull($this->invokeOnWiredResolver("customResponder"));
    }

    /** @throws ReflectionException */
    #[Test]
    public function aPreflightRequestShortCircuitsToTheApplicationItself(): void
    {
        $server = $_SERVER;
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["REQUEST_URI"] = "/orders";
        $_SERVER["REQUEST_METHOD"] = HTTPRequestMethod::options;
        try {
            $application = new ReflectionClass(Application::class)->newInstanceWithoutConstructor();
            $resolver = new FirstResponderResolver($application, new HeadChainLinkResponderFixture());
            $this->assertSame($application, $resolver->firstResponder);
        } finally {
            $_SERVER = $server;
        }
    }

    /** @throws ReflectionException */
    private function invokeOnWiredResolver(string $method, mixed ...$arguments): mixed
    {
        $resolver = new ReflectionClass(FirstResponderResolver::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(FirstResponderResolver::class, "application")->setValue($resolver, new ReflectionClass(Application::class)->newInstanceWithoutConstructor());
        return new ReflectionMethod(FirstResponderResolver::class, $method)->invoke($resolver, ...$arguments);
    }

    /**
     * @param list<string> $fileNames
     * @throws Exception
     */
    private function scratchDirectory(array $fileNames, string $name = "Responders"): URL
    {
        $this->scratchURL = FileManager::default()->temporaryDirectory->appendingPathComponent(new UUID()->uuidString);
        $directoryURL = $this->scratchURL->appendingPathComponent($name);
        FileManager::default()->createDirectory($directoryURL, true);
        new ArrayClass($fileNames)->forEach(fn(string $fileName) => FileManager::default()->createFile($directoryURL->appendingPathComponent($fileName)->path, ""));
        return $directoryURL;
    }

    /** @throws ReflectionException */
    private function invoke(string $method, mixed ...$arguments): mixed
    {
        $resolver = new ReflectionClass(FirstResponderResolver::class)->newInstanceWithoutConstructor();
        return new ReflectionMethod(FirstResponderResolver::class, $method)->invoke($resolver, ...$arguments);
    }
}
