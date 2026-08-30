<?php

// PHPUnit intentionally owns the exception boundary for this test file.
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use LogicException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Service\LLM\LLMAgent;
use Sabatier\Service\LLM\LLMAgentLoop;
use Sabatier\Service\LLM\LLMClient;
use Sabatier\Service\LLM\LLMExecutionDeadline;
use Sabatier\Service\LLM\LLMExecutionPolicy;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;
use Sabatier\Service\LLM\LLMRunStopReason;
use Sabatier\Service\LLM\LLMToolCall;
use Sabatier\Service\LLM\LLMToolCallResult;
use Sabatier\Service\LLM\LLMTurn;
use Sabatier\Service\LLM\PlanExecuteLLMAgentLoop;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use Sabatier\Service\Testing\ConformanceLLMToolExecutor;
use Sabatier\Service\Testing\LLMAgentLoopConformanceTestCase;

final class PlanExecuteLLMAgentLoopConformanceTest extends LLMAgentLoopConformanceTestCase
{
    protected function loop(): LLMAgentLoop
    {
        return new PlanExecuteLLMAgentLoop();
    }

    #[Test]
    public function realToolCallIsRejectedUntilAPlanIsAccepted(): void
    {
        $client = new PlanGateLLMClient();
        $executor = new ConformanceLLMToolExecutor();
        $agent = new LLMAgent($client, $executor, canSpawnSubagents: false, loop: $this->loop());

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "inspect then answer")]));

        $this->assertTrue($run->isComplete);
        $this->assertSame(1, $executor->calls->count);
        $this->assertSame([
            "Submit an ordered plan before executing a real tool.",
            "Plan accepted with 2 steps. Execute it in order, revising it when new evidence requires a change.",
            "tool result",
        ], $run->toolCallResults->map(fn(LLMToolCallResult $result): ?string => $result->content)->array);
        $this->assertTrue($run->toolCallResults->first->isError);
        $this->assertFalse($run->toolCallResults[1]->isError);
    }

    #[Test]
    public function invalidPlanIsCorrectableWithoutExecutingARealTool(): void
    {
        $client = new InvalidThenValidPlanLLMClient();
        $executor = new ConformanceLLMToolExecutor();
        $agent = new LLMAgent($client, $executor, canSpawnSubagents: false, loop: $this->loop());

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "plan carefully")]));

        $this->assertTrue($run->isComplete);
        $this->assertTrue($executor->calls->isEmpty);
        $this->assertSame("A plan requires at least one non-empty string step.", $run->toolCallResults->first->content);
        $this->assertTrue($run->toolCallResults->first->isError);
        $this->assertFalse($run->toolCallResults->last->isError);
    }

    #[Test]
    public function planningUsesTheSharedToolCallBudget(): void
    {
        $policy = new LLMExecutionPolicy(maxToolCalls: 0);
        $agent = new LLMAgent(new InvalidThenValidPlanLLMClient(), new ConformanceLLMToolExecutor(), canSpawnSubagents: false, executionPolicy: $policy, loop: $this->loop());

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "plan carefully")]));

        $this->assertFalse($run->isComplete);
        $this->assertSame(LLMRunStopReason::toolCallLimit, $run->stopReason);
        $this->assertTrue($run->isRetryable);
        $this->assertSame("The shared tool-call budget is exhausted.", $run->toolCallResults->first->content);
    }
}

final class PlanGateLLMClient extends LLMClient
{
    #[Override]
    public string $version {
        get => "test";
    }
    #[Override]
    public int $maxTokens {
        get => 1024;
    }

    private int $call = 0;

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null, ?LLMExecutionDeadline $deadline = null): LLMTurn
    {
        return match ($this->call++) {
            0 => new LLMTurn(null, new ArrayClass([new LLMToolCall("early", "external_tool", new Dictionary())])),
            1 => new LLMTurn(null, new ArrayClass([new LLMToolCall("plan", "submit_plan", Dictionary::dictionaryWithArray(["steps" => ["Inspect", "Answer"]]))])),
            2 => new LLMTurn(null, new ArrayClass([new LLMToolCall("execute", "external_tool", new Dictionary())])),
            3 => new LLMTurn("completed", new ArrayClass()),
            default => throw new LogicException("Unexpected plan-gate turn."),
        };
    }

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    protected function buildRequest(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): URLRequest
    {
        throw new LogicException("Plan-gate client does not build requests.");
    }

    /** @param Dictionary<mixed> $body */
    #[Override]
    protected function parse(Dictionary $body): LLMTurn
    {
        throw new LogicException("Plan-gate client does not parse responses.");
    }
}

final class InvalidThenValidPlanLLMClient extends LLMClient
{
    #[Override]
    public string $version {
        get => "test";
    }
    #[Override]
    public int $maxTokens {
        get => 1024;
    }

    private int $call = 0;

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null, ?LLMExecutionDeadline $deadline = null): LLMTurn
    {
        return match ($this->call++) {
            0 => new LLMTurn(null, new ArrayClass([new LLMToolCall("invalid-plan", "submit_plan", Dictionary::dictionaryWithArray(["steps" => ["  "]]))])),
            1 => new LLMTurn(null, new ArrayClass([new LLMToolCall("valid-plan", "submit_plan", Dictionary::dictionaryWithArray(["steps" => ["Answer"]]))])),
            2 => new LLMTurn("completed", new ArrayClass()),
            default => throw new LogicException("Unexpected plan-validation turn."),
        };
    }

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    protected function buildRequest(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): URLRequest
    {
        throw new LogicException("Plan-validation client does not build requests.");
    }

    /** @param Dictionary<mixed> $body */
    #[Override]
    protected function parse(Dictionary $body): LLMTurn
    {
        throw new LogicException("Plan-validation client does not parse responses.");
    }
}
