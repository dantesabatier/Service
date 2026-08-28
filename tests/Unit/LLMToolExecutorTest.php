<?php

// PHPUnit intentionally owns the exception boundary for this test file.
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use LogicException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Service\LLM\LLMAgent;
use Sabatier\Service\LLM\LLMClient;
use Sabatier\Service\LLM\LLMExecutionDeadline;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;
use Sabatier\Service\LLM\LLMRunStopReason;
use Sabatier\Service\LLM\LLMToolCall;
use Sabatier\Service\LLM\LLMToolExecutionResult;
use Sabatier\Service\LLM\LLMToolExecutor;
use Sabatier\Service\LLM\LLMTurn;
use Sabatier\Service\MCP\Response\ToolDescriptor;

final class LLMToolExecutorTest extends TestCase
{
    #[Test]
    public function agentUsesAReplaceableExecutorForItsCatalogueAndCalls(): void
    {
        $client = new SingleExecutorCallClient();
        $executor = new RecordingLLMToolExecutor();
        $agent = new LLMAgent($client, $executor, canSpawnSubagents: false);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(LLMRunStopReason::done, $run->stopReason);
        $this->assertSame([["external_tool"], ["external_tool"]], $client->toolNamesByCall);
        $this->assertSame("executed externally", $run->messages[1]->content);
        $this->assertSame(["external_tool"], $executor->calls->map(fn(LLMToolCall $call): string => $call->name)->array);
    }

    #[Test]
    public function subagentInheritsTheSameExecutorWithoutAdvertisingRecursion(): void
    {
        $client = new SubagentExecutorClient();
        $executor = new RecordingLLMToolExecutor();
        $agent = new LLMAgent($client, $executor);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "parent task")]));

        $this->assertSame([
            ["external_tool", "run_subagent"],
            ["external_tool"],
            ["external_tool"],
            ["external_tool", "run_subagent"],
        ], $client->toolNamesByCall);
        $this->assertSame(1, $executor->calls->count);
        $this->assertSame(4, $client->deadlines->count);
        $this->assertSame($client->deadlines[0], $client->deadlines[1]);
        $this->assertSame($client->deadlines[0], $client->deadlines[2]);
        $this->assertSame($client->deadlines[0], $client->deadlines[3]);
        $this->assertSame($client->deadlines[0], $executor->deadlines[0]);
        $this->assertSame("parent done", $run->messages->last->content);
    }

    #[Test]
    public function agentStillAppliesWriteApprovalBeforeCallingACustomExecutor(): void
    {
        $executor = new RecordingLLMToolExecutor(false);
        $agent = new LLMAgent(new SingleExecutorCallClient(), $executor, canSpawnSubagents: false);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(LLMRunStopReason::writeApprovalRequired, $run->stopReason);
        $this->assertTrue($executor->calls->isEmpty);
    }
}

final class RecordingLLMToolExecutor implements LLMToolExecutor
{
    /** @var ArrayClass<ToolDescriptor> */
    public ArrayClass $tools {
        get => $this->tools ??= new ArrayClass([new ToolDescriptor("external_tool", "Execute outside the process.", ["type" => "object", "properties" => []])]);
    }
    /** @var ArrayClass<LLMToolCall> */
    public readonly ArrayClass $calls;
    /** @var ArrayClass<LLMExecutionDeadline> */
    public readonly ArrayClass $deadlines;

    /** @param bool $readOnly Whether calls should bypass write approval. */
    public function __construct(private readonly bool $readOnly = true)
    {
        $this->calls = new ArrayClass();
        $this->deadlines = new ArrayClass();
    }

    #[Override]
    public function contains(LLMToolCall $call): bool
    {
        return $call->name === "external_tool";
    }

    #[Override]
    public function isReadOnly(LLMToolCall $call): bool
    {
        return $this->readOnly;
    }

    #[Override]
    public function isCacheable(LLMToolCall $call): bool
    {
        return false;
    }

    #[Override]
    public function execute(LLMToolCall $call, ?LLMExecutionDeadline $deadline = null): LLMToolExecutionResult
    {
        $this->calls->append($call);
        if ($deadline !== null) {
            $this->deadlines->append($deadline);
        }
        return new LLMToolExecutionResult("executed externally");
    }
}

abstract class ExecutorScriptedClient extends LLMClient
{
    /** @var list<list<string>> */
    public array $toolNamesByCall = [];
    /** @var ArrayClass<LLMExecutionDeadline> */
    public ArrayClass $deadlines {
        get => $this->deadlines ??= new ArrayClass();
    }

    #[Override]
    public string $version {
        get => "test";
    }
    #[Override]
    public int $maxTokens {
        get => 1024;
    }

    /** @param ArrayClass<ToolDescriptor> $tools */
    protected function recordTools(ArrayClass $tools): void
    {
        $this->toolNamesByCall[] = $tools->map(fn(ToolDescriptor $tool): string => $tool->name)->array;
    }

    protected function recordDeadline(?LLMExecutionDeadline $deadline): void
    {
        if ($deadline !== null) {
            $this->deadlines->append($deadline);
        }
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

final class SingleExecutorCallClient extends ExecutorScriptedClient
{
    private int $call = 0;

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null, ?LLMExecutionDeadline $deadline = null): LLMTurn
    {
        $this->recordTools($tools);
        $this->recordDeadline($deadline);
        return match ($this->call++) {
            0 => new LLMTurn(null, new ArrayClass([new LLMToolCall("external-1", "external_tool", new Dictionary())])),
            1 => new LLMTurn("done", new ArrayClass()),
            default => throw new LogicException("Unexpected LLM call."),
        };
    }
}

final class SubagentExecutorClient extends ExecutorScriptedClient
{
    private int $call = 0;

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null, ?LLMExecutionDeadline $deadline = null): LLMTurn
    {
        $this->recordTools($tools);
        $this->recordDeadline($deadline);
        return match ($this->call++) {
            0 => new LLMTurn(null, new ArrayClass([new LLMToolCall("parent-subagent", "run_subagent", new Dictionary(["task" => "inspect one thing"]))])),
            1 => new LLMTurn(null, new ArrayClass([new LLMToolCall("child-external", "external_tool", new Dictionary())])),
            2 => new LLMTurn("sub answer", new ArrayClass()),
            3 => new LLMTurn("parent done", new ArrayClass()),
            default => throw new LogicException("Unexpected LLM call."),
        };
    }
}
