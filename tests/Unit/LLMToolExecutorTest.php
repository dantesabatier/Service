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
use Sabatier\Service\LLM\LLMRunEvent;
use Sabatier\Service\LLM\LLMRunObserver;
use Sabatier\Service\LLM\LLMToolCall;
use Sabatier\Service\LLM\LLMToolCallDisposition;
use Sabatier\Service\LLM\LLMToolCallFinishedEvent;
use Sabatier\Service\LLM\LLMToolExecutionResult;
use Sabatier\Service\LLM\LLMToolExecutor;
use Sabatier\Service\LLM\LLMToolProviderException;
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

    #[Test]
    public function transientToolProviderFailureReturnsARetryableRunAndClosesTheToolSpan(): void
    {
        $observer = new ToolExecutorRecordingObserver();
        $agent = new LLMAgent(new SingleExecutorCallClient(), new ProviderFailingLLMToolExecutor(true), canSpawnSubagents: false, observer: $observer);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        /** @var LLMToolCallFinishedEvent $event */
        $event = $observer->events->first(fn(LLMRunEvent $candidate): bool => $candidate instanceof LLMToolCallFinishedEvent);
        $this->assertSame(LLMRunStopReason::toolProviderFailure, $run->stopReason);
        $this->assertTrue($run->isRetryable);
        $this->assertSame(LLMToolCallDisposition::providerFailure, $event->disposition);
        $this->assertNull($run->toolCallResults[0]->content);
    }

    #[Test]
    public function catalogueProviderFailureStopsBeforeTheFirstModelTurn(): void
    {
        $client = new SingleExecutorCallClient();
        $agent = new LLMAgent($client, new ProviderFailingLLMToolExecutor(false, true), canSpawnSubagents: false);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(LLMRunStopReason::toolProviderFailure, $run->stopReason);
        $this->assertFalse($run->isRetryable);
        $this->assertTrue($run->messages->isEmpty);
        $this->assertSame([], $client->toolNamesByCall);
    }
}

final class ToolExecutorRecordingObserver implements LLMRunObserver
{
    /** @var ArrayClass<LLMRunEvent> */
    public readonly ArrayClass $events;

    public function __construct()
    {
        $this->events = new ArrayClass();
    }

    #[Override]
    public function observe(LLMRunEvent $event): void
    {
        $this->events->append($event);
    }
}

final class ProviderFailingLLMToolExecutor implements LLMToolExecutor
{
    /** @var ArrayClass<ToolDescriptor> */
    #[Override]
    public ArrayClass $tools {
        get => $this->failOnCatalogue ? throw new LLMToolProviderException("catalogue unavailable", $this->isTransient) : new ArrayClass([new ToolDescriptor("external_tool", "Execute outside the process.", ["type" => "object", "properties" => []])]);
    }

    /**
     * @param bool $isTransient Whether the simulated provider failure is retryable.
     * @param bool $failOnCatalogue Whether failure occurs while loading descriptors instead of executing.
     */
    public function __construct(private readonly bool $isTransient, private readonly bool $failOnCatalogue = false)
    {
    }

    #[Override]
    public function contains(LLMToolCall $call): bool
    {
        return true;
    }

    #[Override]
    public function isReadOnly(LLMToolCall $call): bool
    {
        return true;
    }

    #[Override]
    public function isCacheable(LLMToolCall $call): bool
    {
        return false;
    }

    #[Override]
    public function execute(LLMToolCall $call, ?LLMExecutionDeadline $deadline = null): LLMToolExecutionResult
    {
        throw new LLMToolProviderException("tool provider unavailable", $this->isTransient);
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
