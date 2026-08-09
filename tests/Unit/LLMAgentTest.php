<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Service\LLM\LLMAgent;
use Sabatier\Service\LLM\LLMClient;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;
use Sabatier\Service\LLM\LLMToolCall;
use Sabatier\Service\LLM\LLMTurn;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\ToolRegistry;

final class LLMAgentTest extends TestCase
{
    #[Test]
    public function subagentUsesSameRealToolsWithoutRecursiveSubagentToolAndChargesParentRun(): void
    {
        $client = new ScriptedSubagentClient();
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->tool()])));

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "parent task")]), "system");

        $this->assertSame([["probe_tool", "run_subagent"], ["probe_tool"], ["probe_tool"], ["probe_tool", "run_subagent"]], $client->toolNamesByCall);
        $this->assertSame(28, $run->inputTokens);
        $this->assertSame(10, $run->outputTokens);
        $this->assertSame("parent done", $run->messages->last->content);

        $this->assertSame("sub answer", $run->messages[1]->content);
    }

    private function tool(): AbstractTool
    {
        /** @var AbstractTool */
        return (new ReflectionClass(LLMAgentProbeTool::class))->newInstanceWithoutConstructor();
    }
}

final class ScriptedSubagentClient extends LLMClient
{
    public string $version {
        get => "test";
    }
    public int $maxTokens {
        get => 1024;
    }

    /** @var list<list<string>> */
    public array $toolNamesByCall = [];
    private int $call = 0;

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): LLMTurn
    {
        $this->toolNamesByCall[] = $tools->map(fn(ToolDescriptor $tool): string => $tool->name)->array;
        return match ($this->call++) {
            0 => new LLMTurn(null, new ArrayClass([new LLMToolCall("parent-subagent", "run_subagent", new Dictionary(["task" => "inspect one thing"]))]), 10, 2),
            1 => new LLMTurn(null, new ArrayClass([new LLMToolCall("sub-tool", "probe_tool", new Dictionary())]), 7, 3),
            2 => new LLMTurn("sub answer", new ArrayClass(), 5, 4),
            3 => new LLMTurn("parent done", new ArrayClass(), 6, 1),
            default => throw new LogicException("Unexpected LLM call."),
        };
    }

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    protected function buildRequest(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): URLRequest
    {
        throw new LogicException("Scripted client does not build requests.");
    }

    /** @param Dictionary<mixed> $body */
    protected function parse(Dictionary $body): LLMTurn
    {
        throw new LogicException("Scripted client does not parse responses.");
    }
}

final class LLMAgentProbeTool extends AbstractTool
{
    public string $name {
        get => "probe_tool";
    }
    public string $description {
        get => "Probe tool.";
    }
    public array $inputSchema {
        get => ["type" => "object"];
    }

    /** @return ArrayClass<ContentItem> */
    public function execute(Dictionary $arguments): ArrayClass
    {
        return new ArrayClass([new ContentItem("text", "probe result")]);
    }
}
