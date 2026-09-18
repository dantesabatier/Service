<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Service\Outlet;
use Sabatier\Service\Renderer;
use Sabatier\Service\ViewController;

final class OutletProbeViewController extends ViewController
{
    #[Outlet]
    public string $username = "ada";
    #[Outlet]
    public int $unreadCount = 3;
    public string $secret = "not an outlet";

    public bool $willLoadCalled = false;
    public bool $didLoadCalled = false;

    public function viewWillLoad(): void
    {
        $this->willLoadCalled = true;
    }

    public function viewDidLoad(): void
    {
        $this->didLoadCalled = true;
    }
}

final class RenamedProbeViewController extends ViewController
{
    protected string $name {
        get => "custom-template";
    }
}

final class ViewControllerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = $_SERVER;
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["REQUEST_URI"] = "/Home";
        $_SERVER["REQUEST_METHOD"] = "GET";
        ObjectClass::$staticAssociatedValues = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        ObjectClass::$staticAssociatedValues = [];
        parent::tearDown();
    }

    #[Test]
    public function theTemplateIsNamedAfterTheControllerByDefault(): void
    {
        $this->assertSame("OutletProbeViewController", $this->templateName(new OutletProbeViewController()));
    }

    #[Test]
    public function aControllerMayDecoupleItsTemplateFromItsClassName(): void
    {
        $this->assertSame("custom-template", $this->templateName(new RenamedProbeViewController()));
    }

    #[Test]
    public function onlyAnnotatedPropertiesReachTheTemplate(): void
    {
        $context = $this->context(new OutletProbeViewController());
        $this->assertArrayHasKey("username", $context);
        $this->assertArrayHasKey("unreadCount", $context);
        $this->assertArrayNotHasKey("secret", $context);
    }

    #[Test]
    public function eachOutletCarriesItsCurrentValue(): void
    {
        $controller = new OutletProbeViewController();
        $controller->username = "grace";
        $context = $this->context($controller);
        $this->assertSame("grace", $context["username"]);
        $this->assertSame(3, $context["unreadCount"]);
    }

    #[Test]
    public function theTitleIsAnOutletOfItsOwn(): void
    {
        $this->assertArrayHasKey("title", $this->context(new OutletProbeViewController()));
    }

    #[Test]
    public function theContextIsBuiltOnceAndReused(): void
    {
        $controller = new OutletProbeViewController();
        $first = $this->context($controller);
        $controller->username = "changed after the first read";
        $this->assertSame($first, $this->context($controller));
    }

    #[Test]
    public function theTitleDefaultsToTheBundleName(): void
    {
        $this->assertSame(Bundle::main()->object("CFBundleName"), new OutletProbeViewController()->title);
    }

    #[Test]
    public function anAssignedTitleIsKept(): void
    {
        $controller = new OutletProbeViewController();
        $controller->title = "Dashboard";
        $this->assertSame("Dashboard", $controller->title);
    }

    #[Test]
    public function theTemplateBundleIsTheMainBundleByDefault(): void
    {
        $this->assertSame(Bundle::main(), new OutletProbeViewController()->bundle);
    }

    #[Test]
    public function noViewIsLoadedUntilItIsAskedFor(): void
    {
        $controller = new OutletProbeViewController();
        $this->assertFalse($controller->isViewLoaded);
        $this->assertFalse($controller->willLoadCalled);
        $this->assertFalse($controller->didLoadCalled);
    }

    #[Test]
    public function theRendererIsSwappableForTheWholeApplication(): void
    {
        $this->assertSame(Renderer::class, ViewController::$rendererClass);
    }

    private function templateName(ViewController $controller): string
    {
        /** @var string */
        return new ReflectionProperty(ViewController::class, "name")->getValue($controller);
    }

    /** @return array<string, mixed> */
    private function context(ViewController $controller): array
    {
        /** @var array<string, mixed> */
        return new ReflectionProperty(ViewController::class, "context")->getValue($controller);
    }
}
