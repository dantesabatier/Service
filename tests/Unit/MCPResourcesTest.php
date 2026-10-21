<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\JSONRPCError;
use Sabatier\Service\MCP\Resources\AbstractResource;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Resources\ResourceRegistry;
use Sabatier\Service\MCP\ResourcesListHandler;
use Sabatier\Service\MCP\ResourcesReadHandler;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Response\ReadResourceResult;
use Sabatier\Service\MCP\Response\ResourceDescriptor;
use Sabatier\Service\MCP\Response\ResourcesListResult;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use Sabatier\Service\MCP\RPCMessage;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use const Sabatier\Service\MCP\JSONRPCErrorCodeInvalidParams;
use const Sabatier\Service\MCPAppMimeType;

/**
 * Covers what a host sees of a published resource: the catalogue, the contents it reads by uri, and the refusal a uri
 * naming nothing earns.
 */
final class MCPResourcesTest extends TestCase
{
    #[Test]
    public function aResourceIsAdvertisedUnderTheLastSegmentOfItsUri(): void
    {
        /** @var ResourceDescriptor $descriptor */
        $descriptor = $this->registry()->list->first;
        $this->assertSame("dashboard", $descriptor->name);
        $this->assertSame("ui://probe/dashboard", $descriptor->uri);
        $this->assertSame(MCPAppMimeType, $descriptor->mimeType);
    }

    #[Test]
    public function theCatalogueListsEveryPublishedResource(): void
    {
        $result = new ResourcesListHandler($this->registry())->handle($this->message("resources/list"));
        $this->assertInstanceOf(ResourcesListResult::class, $result);
        $this->assertSame(1, $result->resources->count);
    }

    #[Test]
    public function readingAResourceAnswersItsMarkupAndItsUIDeclaration(): void
    {
        $result = new ResourcesReadHandler($this->registry())->handle($this->message("resources/read", ["uri" => "ui://probe/dashboard"]));
        $this->assertInstanceOf(ReadResourceResult::class, $result);
        $contents = $result->contents->first;
        $this->assertSame("<!doctype html><title>Probe</title>", $contents?->text);
        $this->assertSame(["ui" => ["prefersBorder" => true]], $contents?->jsonSerialize()["_meta"] ?? null);
    }

    #[Test]
    public function anUnknownUriIsRefusedRatherThanAnsweredWithNothing(): void
    {
        $result = new ResourcesReadHandler($this->registry())->handle($this->message("resources/read", ["uri" => "ui://probe/nothing-here"]));
        $this->assertInstanceOf(JSONRPCError::class, $result);
        $this->assertSame(JSONRPCErrorCodeInvalidParams, $result->code);
    }

    #[Test]
    public function aReadWithoutAUriIsRefused(): void
    {
        $result = new ResourcesReadHandler($this->registry())->handle($this->message("resources/read"));
        $this->assertInstanceOf(JSONRPCError::class, $result);
        $this->assertSame(JSONRPCErrorCodeInvalidParams, $result->code);
    }

    #[Test]
    public function aToolDeclaringAUIResourceAdvertisesItInItsMeta(): void
    {
        /** @var ToolDescriptor $descriptor */
        $descriptor = new ToolRegistry(new ArrayClass([new ReflectionClass(UIBoundProbeTool::class)->newInstanceWithoutConstructor()]))->list->first;
        $this->assertSame(["ui" => ["resourceUri" => "ui://probe/dashboard"]], $descriptor->jsonSerialize()["_meta"] ?? null);
    }

    #[Test]
    public function aToolDeclaringNoneCarriesNoMetaAtAll(): void
    {
        /** @var ToolDescriptor $descriptor */
        $descriptor = new ToolRegistry(new ArrayClass([new ReflectionClass(PlainProbeTool::class)->newInstanceWithoutConstructor()]))->list->first;
        $this->assertArrayNotHasKey("_meta", $descriptor->jsonSerialize());
    }

    private function registry(): ResourceRegistry
    {
        return new ResourceRegistry(new ArrayClass([new ProbeResource()]));
    }

    /** @param array<string, mixed> $params */
    private function message(string $method, array $params = []): RPCMessage
    {
        return new RPCMessage(1, $method, new Dictionary($params));
    }
}

abstract class ResourceProbeToolBase extends AbstractTool
{
    #[Override]
    public array $inputSchema {
        get => ["type" => "object"];
    }

    #[Override]
    public function authorizationResource(Dictionary $arguments): ?string
    {
        return null;
    }

    /** @return ArrayClass<ContentItem> */
    #[Override]
    protected function executeCore(Dictionary $arguments): ArrayClass
    {
        return $this->jsonResult(["ok" => true]);
    }
}

final class UIBoundProbeTool extends ResourceProbeToolBase
{
    #[Override]
    public string $name {
        get => "ui_bound_probe";
    }
    #[Override]
    public ?string $uiResourceUri {
        get => "ui://probe/dashboard";
    }
}

final class PlainProbeTool extends ResourceProbeToolBase
{
    #[Override]
    public string $name {
        get => "plain_probe";
    }
}

final class ProbeResource extends AbstractResource
{
    #[Override]
    public string $uri {
        get => "ui://probe/dashboard";
    }
    #[Override]
    public string $filename {
        get => "probe.html";
    }
    #[Override]
    public ?array $ui {
        get => ["prefersBorder" => true];
    }
    #[Override]
    public string $title {
        get => "Probe";
    }
    #[Override]
    public ?string $description {
        get => null;
    }

    #[Override]
    protected function markup(): string
    {
        return "<!doctype html><title>Probe</title>";
    }
}
