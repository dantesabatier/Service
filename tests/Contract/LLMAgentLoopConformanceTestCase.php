<?php

// PHPUnit intentionally owns the exception boundary for this test file.
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Sabatier\Service\Testing;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\LLM\LLMAgent;
use Sabatier\Service\LLM\LLMAgentLoop;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;
use Sabatier\Service\LLM\LLMRunStopReason;

/**
 * Reusable behavioral contract for an `LLMAgentLoop` implementation.
 *
 * Extension authors only provide the strategy under test. The suite drives it through the real
 * runtime so completion, pending tool results, iteration caps and write approval cannot be faked
 * by a session test double.
 */
abstract class LLMAgentLoopConformanceTestCase extends TestCase
{
    abstract protected function loop(): LLMAgentLoop;

    #[Test]
    final public function directCompletionProducesARuntimeOwnedRun(): void
    {
        $agent = new LLMAgent(new ConformanceLLMClient(true), new ConformanceLLMToolExecutor(), canSpawnSubagents: false, loop: $this->loop());

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "answer directly")]));

        $this->assertTrue($run->isComplete);
        $this->assertSame(LLMRunStopReason::done, $run->stopReason);
        $this->assertSame("completed", $run->messages->last->content);
        $this->assertSame(5, $run->inputTokens);
        $this->assertSame(3, $run->outputTokens);
    }

    #[Test]
    final public function guardedToolRoundTripCompletes(): void
    {
        $executor = new ConformanceLLMToolExecutor();
        $agent = new LLMAgent(new ConformanceLLMClient(), $executor, canSpawnSubagents: false, loop: $this->loop());

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "use the tool")]));

        $this->assertTrue($run->isComplete);
        $this->assertSame(1, $executor->calls->count);
        $this->assertSame("external_tool", $executor->calls->first->name);
        $this->assertSame("completed", $run->messages->last->content);
    }

    #[Test]
    final public function iterationCapCannotBeBypassed(): void
    {
        $agent = new LLMAgent(new ConformanceLLMClient(repeatTool: true), new ConformanceLLMToolExecutor(), maxIterations: 2, canSpawnSubagents: false, loop: $this->loop());

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "never finish")]));

        $this->assertFalse($run->isComplete);
        $this->assertSame(LLMRunStopReason::iterationCap, $run->stopReason);
        $this->assertTrue($run->isRetryable);
    }

    #[Test]
    final public function writeApprovalRemainsARuntimeBoundary(): void
    {
        $executor = new ConformanceLLMToolExecutor(false);
        $agent = new LLMAgent(new ConformanceLLMClient(), $executor, canSpawnSubagents: false, loop: $this->loop());

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "change state")]));

        $this->assertFalse($run->isComplete);
        $this->assertSame(LLMRunStopReason::writeApprovalRequired, $run->stopReason);
        $this->assertTrue($executor->calls->isEmpty);
    }
}
