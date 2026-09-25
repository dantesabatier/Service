<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Service\Application;
use Sabatier\Service\ApplicationDelegate;
use Throwable;

final class RecordingApplicationDelegate implements ApplicationDelegate
{
    public int $willFinishLaunchingCount = 0;
    public int $didFinishLaunchingCount = 0;
    public int $willTerminateCount = 0;
    public ?Throwable $crash = null;

    #[Override]
    public function applicationWillFinishLaunching(Application $application): void
    {
        $this->willFinishLaunchingCount++;
    }

    #[Override]
    public function applicationDidFinishLaunching(Application $application): void
    {
        $this->didFinishLaunchingCount++;
    }

    #[Override]
    public function applicationWillTerminate(Application $application): void
    {
        $this->willTerminateCount++;
    }

    #[Override]
    public function applicationDidCrash(Application $application, Throwable $throwable): void
    {
        $this->crash = $throwable;
    }
}

/**
 * Covers the shutdown hook the run loop registers. A real fatal cannot be staged from inside the
 * process, so the crash branch is reached through the error state PHP leaves behind rather than by
 * provoking one.
 */
final class ApplicationShutdownTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ObjectClass::$staticAssociatedValues = [];
    }

    protected function tearDown(): void
    {
        ObjectClass::$staticAssociatedValues = [];
        parent::tearDown();
    }

    /** @throws ReflectionException */
    #[Test]
    public function aCleanShutdownTellsTheDelegateTheApplicationIsEnding(): void
    {
        [$application, $delegate] = $this->application();
        $this->shutDown($application);
        $this->assertSame(1, $delegate->willTerminateCount);
        $this->assertNull($delegate->crash);
    }

    /** @throws ReflectionException */
    #[Test]
    public function shuttingDownTwiceTellsTheDelegateEachTime(): void
    {
        [$application, $delegate] = $this->application();
        $this->shutDown($application);
        $this->shutDown($application);
        $this->assertSame(2, $delegate->willTerminateCount);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aShutdownAfterAnOrderlyTerminationDoesNotReportACrash(): void
    {
        [$application, $delegate] = $this->application();
        new ReflectionProperty(Application::class, "isTerminated")->setValue($application, true);
        $this->shutDown($application);
        $this->assertNull($delegate->crash);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theDelegateIsNotToldItLaunchedByAShutdown(): void
    {
        [$application, $delegate] = $this->application();
        $this->shutDown($application);
        $this->assertSame(0, $delegate->willFinishLaunchingCount);
        $this->assertSame(0, $delegate->didFinishLaunchingCount);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aRequestThatIsNotPreflightIsLeftAlone(): void
    {
        $server = $_SERVER;
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["REQUEST_URI"] = "/orders";
        $_SERVER["REQUEST_METHOD"] = "GET";
        try {
            new ReflectionMethod(Application::class, "handlePreflightIfNeeded")->invoke(new ReflectionClass(Application::class)->newInstanceWithoutConstructor());
            $this->expectNotToPerformAssertions();
        } finally {
            $_SERVER = $server;
        }
    }

    /** @throws ReflectionException */
    private function shutDown(Application $application): void
    {
        new ReflectionMethod(Application::class, "handleShutdown")->invoke($application);
    }

    /**
     * @return array{Application, RecordingApplicationDelegate}
     * @throws ReflectionException
     */
    private function application(): array
    {
        $application = new ReflectionClass(Application::class)->newInstanceWithoutConstructor();
        $delegate = new RecordingApplicationDelegate();
        new ReflectionProperty(Application::class, "delegate")->setRawValue($application, $delegate);
        new ReflectionProperty(Application::class, "isTerminated")->setValue($application, false);
        return [$application, $delegate];
    }
}
