<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Exception;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Service\LLM\LLMAgent;
use Sabatier\Service\LLM\LLMClient;
use Sabatier\Service\LLM\LLMExecutionDeadline;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;
use Sabatier\Service\LLM\LLMToolCall;
use Sabatier\Service\LLM\LLMTurn;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use Sabatier\Service\MCP\Response\ToolCallResult;
use Sabatier\Service\MCP\RPCMessage;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Sabatier\Service\MCP\ToolsCallHandler;
use Throwable;
use const Sabatier\Service\MCPProtocolVersionLatestStable;
use const Sabatier\Service\MCPProtocolVersionLegacy;
use const Sabatier\Service\MCPProtocolVersionStable;

/**
 * Covers the payload a tool contributes for a UI to read: where it is captured, that the text the model reads is
 * unchanged by it, and that it never survives into the next call of the same instance.
 */
final class StructuredContentTest extends TestCase
{
    /** @throws Exception */
    #[Test]
    public function aJSONResultKeepsItsDataAsTheStructuredPayload(): void
    {
        $tool = $this->tool(StructuredProbeTool::class);
        $tool->call(new Dictionary());
        $this->assertSame(["rowCount" => 1, "results" => ["ok"]], $tool->structuredContent);
    }

    /** @throws Exception */
    #[Test]
    public function theTextTheModelReadsIsTheSerializationItAlwaysWas(): void
    {
        $tool = $this->tool(StructuredProbeTool::class);
        $this->assertSame("{\"rowCount\":1,\"results\":[\"ok\"]}", $tool->call(new Dictionary())->first?->text);
    }

    /** @throws Exception */
    #[Test]
    public function aPayloadTakenFromACollectionAnswersWithTheShapeTheClientIsSent(): void
    {
        $tool = $this->tool(CollectionStructuredProbeTool::class);
        $tool->call(new Dictionary());
        $this->assertSame(["entity" => "Widget", "count" => 2], $tool->structuredContent);
    }

    /** @throws Exception */
    #[Test]
    public function aPayloadThatSerializesToAJSONArrayIsLeftOut(): void
    {
        $tool = $this->tool(ListStructuredProbeTool::class);
        $tool->call(new Dictionary());
        $this->assertNull($tool->structuredContent);
    }

    /** @throws Exception */
    #[Test]
    public function aToolWhoseTextIsProseContributesItsPayloadOnItsOwn(): void
    {
        $tool = $this->tool(ProseStructuredProbeTool::class);
        $this->assertSame("Saved.", $tool->call(new Dictionary())->first?->text);
        $this->assertSame(["saved" => true], $tool->structuredContent);
    }

    /** @throws Exception */
    #[Test]
    public function aCallThatContributesNothingDoesNotInheritTheLastPayload(): void
    {
        $tool = $this->tool(AlternatingStructuredProbeTool::class);
        $tool->call(new Dictionary(["structured" => true]));
        $this->assertSame(["rowCount" => 1, "results" => ["ok"]], $tool->structuredContent);
        $tool->call(new Dictionary());
        $this->assertNull($tool->structuredContent);
    }

    /** @throws Throwable */
    #[Test]
    public function theRegistryCarriesThePayloadOutOfTheCall(): void
    {
        $result = new ToolRegistry(new ArrayClass([$this->tool(StructuredProbeTool::class)]))->call("structured_probe", new Dictionary());
        $this->assertSame(["rowCount" => 1, "results" => ["ok"]], $result->structuredContent);
        $this->assertSame("{\"rowCount\":1,\"results\":[\"ok\"]}", $result->text);
    }

    /** @throws Throwable */
    #[Test]
    public function aFailureCarriesNoPayload(): void
    {
        $result = new ToolRegistry(new ArrayClass([$this->tool(StructuredProbeTool::class)]))->call("structured_probe", new Dictionary(["unknown" => true]));
        $this->assertTrue($result->isError);
        $this->assertNull($result->structuredContent);
    }

    /** @throws Throwable */
    #[Test]
    public function anAgentRunKeepsThePayloadBesideTheTextTheModelRead(): void
    {
        $agent = new LLMAgent(new StructuredProbeCallClient(), new ToolRegistry(new ArrayClass([$this->tool(StructuredProbeTool::class)])), canSpawnSubagents: false);
        $result = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]))->toolCallResults[0];
        $this->assertSame("{\"rowCount\":1,\"results\":[\"ok\"]}", $result->content);
        $this->assertSame(["rowCount" => 1, "results" => ["ok"]], $result->structuredContent);
    }

    /** @throws Throwable */
    #[Test]
    public function anAgentRunOverAProseToolKeepsNoPayload(): void
    {
        $agent = new LLMAgent(new StructuredProbeCallClient(), new ToolRegistry(new ArrayClass([$this->tool(AlternatingStructuredProbeTool::class)])), canSpawnSubagents: false);
        $this->assertNull($agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]))->toolCallResults[0]->structuredContent);
    }

    /** @throws Throwable */
    #[Test]
    public function aNegotiatedRevisionThatDefinesTheFieldCarriesIt(): void
    {
        foreach ([MCPProtocolVersionStable, MCPProtocolVersionLatestStable] as $version) {
            $this->assertSame(["rowCount" => 1, "results" => ["ok"]], $this->envelope($version)["structuredContent"] ?? null, "Expected $version to carry the payload.");
        }
    }

    /** @throws Throwable */
    #[Test]
    public function theLegacyRevisionLeavesTheFieldOutEntirely(): void
    {
        $envelope = $this->envelope(MCPProtocolVersionLegacy);
        $this->assertArrayNotHasKey("structuredContent", $envelope);
        $this->assertArrayHasKey("content", $envelope);
    }

    /**
     * @return array<array-key, mixed>
     * @throws Throwable
     */
    private function envelope(string $protocolVersion): array
    {
        $registry = new ToolRegistry(new ArrayClass([$this->tool(StructuredProbeTool::class)]));
        $message = new RPCMessage(1, "tools/call", new Dictionary(["name" => "structured_probe", "arguments" => new Dictionary()]));
        /** @var ToolCallResult $result */
        $result = new ToolsCallHandler($registry, $protocolVersion)->handle($message);
        return $result->jsonSerialize();
    }

    /**
     * @template T of AbstractTool
     * @param class-string<T> $toolClass
     * @return T
     * @throws ReflectionException
     * @noinspection PhpIncompatibleReturnTypeInspection
     */
    private function tool(string $toolClass): AbstractTool
    {
        return new ReflectionClass($toolClass)->newInstanceWithoutConstructor();
    }
}

abstract class StructuredProbeToolBase extends AbstractTool
{
    #[Override]
    public string $name {
        get => "structured_probe";
    }
    #[Override]
    public bool $isReadOnly {
        get => true;
    }
    #[Override]
    public array $inputSchema {
        get => ["type" => "object", "properties" => ["structured" => ["type" => "boolean"]]];
    }

    #[Override]
    public function authorizationResource(Dictionary $arguments): ?string
    {
        return null;
    }
}

final class StructuredProbeTool extends StructuredProbeToolBase
{
    /** @return ArrayClass<ContentItem> */
    #[Override]
    protected function executeCore(Dictionary $arguments): ArrayClass
    {
        return $this->jsonResult(["rowCount" => 1, "results" => ["ok"]]);
    }
}

final class CollectionStructuredProbeTool extends StructuredProbeToolBase
{
    /** @return ArrayClass<ContentItem> */
    #[Override]
    protected function executeCore(Dictionary $arguments): ArrayClass
    {
        return $this->jsonResult(new Dictionary(["entity" => "Widget", "count" => 2]));
    }
}

final class ListStructuredProbeTool extends StructuredProbeToolBase
{
    /** @return ArrayClass<ContentItem> */
    #[Override]
    protected function executeCore(Dictionary $arguments): ArrayClass
    {
        return $this->jsonResult(new ArrayClass(["first", "second"]));
    }
}

final class ProseStructuredProbeTool extends StructuredProbeToolBase
{
    /** @return ArrayClass<ContentItem> */
    #[Override]
    protected function executeCore(Dictionary $arguments): ArrayClass
    {
        return $this->structuredResult($this->textResult("Saved."), ["saved" => true]);
    }
}

final class AlternatingStructuredProbeTool extends StructuredProbeToolBase
{
    /** @return ArrayClass<ContentItem> */
    #[Override]
    protected function executeCore(Dictionary $arguments): ArrayClass
    {
        return $arguments["structured"] === true ? $this->jsonResult(["rowCount" => 1, "results" => ["ok"]]) : $this->textResult("nothing structured here");
    }
}

final class StructuredProbeCallClient extends LLMClient
{
    private int $call = 0;
    #[Override]
    public string $version {
        get => "test";
    }
    #[Override]
    public int $maxTokens {
        get => 1024;
    }

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null, ?LLMExecutionDeadline $deadline = null): LLMTurn
    {
        return match ($this->call++) {
            0 => new LLMTurn(null, new ArrayClass([new LLMToolCall("probe-1", "structured_probe", new Dictionary())])),
            1 => new LLMTurn("done", new ArrayClass()),
            default => throw new LogicException("Unexpected LLM call."),
        };
    }

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    protected function buildRequest(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): URLRequest
    {
        throw new LogicException("Scripted client does not build requests.");
    }

    /** @param Dictionary<mixed> $body */
    #[Override]
    protected function parse(Dictionary $body): LLMTurn
    {
        throw new LogicException("Scripted client does not parse responses.");
    }
}
