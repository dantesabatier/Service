<?php

// PHPUnit intentionally owns the exception boundary for this test file.
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use LogicException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Service\LLM\LLMAgent;
use Sabatier\Service\LLM\LLMAgentLoop;
use Sabatier\Service\LLM\LLMAgentRuntime;
use Sabatier\Service\LLM\LLMClient;
use Sabatier\Service\LLM\LLMExecutionDeadline;
use Sabatier\Service\LLM\LLMExecutionPolicy;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;
use Sabatier\Service\LLM\LLMProviderException;
use Sabatier\Service\LLM\LLMRun;
use Sabatier\Service\LLM\LLMRunStopReason;
use Sabatier\Service\LLM\LLMToolCall;
use Sabatier\Service\LLM\LLMTurn;
use Sabatier\Service\LLM\LLMTurnStopReason;
use Sabatier\Service\LLM\ReActLLMAgentLoop;
use Sabatier\Service\LLM\WindowedLLMContextAssembler;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\ToolRegistry;

final class LLMAgentTest extends TestCase
{
    #[Test]
    public function delegatesToAReplaceableLoopThroughAnIsolatedGuardedRuntime(): void
    {
        $messages = new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]);
        $loop = new RecordingAgentLoop();
        $agent = new LLMAgent(new ScriptedTerminalTurnClient(LLMTurnStopReason::completed), new ToolRegistry(new ArrayClass([$this->tool()])), maxIterations: 7, canSpawnSubagents: false, loop: $loop);

        $run = $agent->run($messages, "policy");

        $this->assertSame(1, $messages->count);
        $this->assertSame("task", $loop->query);
        $this->assertSame("policy", $loop->systemPrompt);
        $this->assertSame(7, $loop->maxIterations);
        $this->assertFalse($loop->canSpawnSubagents);
        $this->assertSame(["probe_tool"], $loop->toolNames);
        $this->assertSame("partial", $run->messages->last->content);
        $this->assertSame(3, $run->inputTokens);
        $this->assertSame(4, $run->outputTokens);
    }

    #[Test]
    public function replaceableLoopInheritsWriteApprovalWithoutReimplementingIt(): void
    {
        $agent = new LLMAgent(new ScriptedReorderedArgumentsClient("writing_tool"), new ToolRegistry(new ArrayClass([$this->writingTool()])), canSpawnSubagents: false, loop: new RuntimeToolUsingLoop());

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(LLMRunStopReason::writeApprovalRequired, $run->stopReason);
        $this->assertTrue($run->messages->last->isError);
        $this->assertStringContainsString("requires explicit user approval", (string)$run->messages->last->content);
    }

    #[Test]
    public function replaceableLoopCannotExceedTheRuntimeIterationCap(): void
    {
        $agent = new LLMAgent(new ScriptedNeverDoneClient(), new ToolRegistry(new ArrayClass([$this->tool()])), maxIterations: 2, canSpawnSubagents: false, loop: new RuntimeToolUsingLoop());

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(LLMRunStopReason::iterationCap, $run->stopReason);
        $this->assertSame(2, $run->messages->filter(fn(LLMMessage $message): bool => $message->role === LLMMessageRole::assistant)->count);
    }

    #[Test]
    public function subagentUsesSameRealToolsWithoutRecursiveSubagentToolAndChargesParentRun(): void
    {
        $client = new ScriptedSubagentClient();
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->tool()])));

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "parent task")]), "system");

        $this->assertSame([["probe_tool", "run_subagent"], ["probe_tool"], ["probe_tool"], ["probe_tool", "run_subagent"]], $client->toolNamesByCall);
        $this->assertSame(["system", "system", "system", "system"], $client->systemPromptsByCall);
        $this->assertSame(["task", "context"], array_keys($client->subagentInputSchema["properties"]));
        $this->assertSame(28, $run->inputTokens);
        $this->assertSame(10, $run->outputTokens);
        $this->assertSame("parent done", $run->messages->last->content);

        $this->assertSame("sub answer", $run->messages[1]->content);
    }

    #[Test]
    public function subagentNoAnswerIsFlaggedAsToolError(): void
    {
        $client = new ScriptedNoAnswerSubagentClient();
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->tool()])));

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "parent task")]), "system");

        $this->assertSame("The subagent produced no answer.", $run->messages[1]->content);
        $this->assertTrue($run->messages[1]->isError);
    }

    #[Test]
    public function runStoppedByIterationCapIsIncomplete(): void
    {
        $client = new ScriptedNeverDoneClient();
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->tool()])), 3, false);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertFalse($run->isComplete);
        $this->assertSame(3, $client->calls);
        $this->assertSame(LLMMessageRole::tool, $run->messages->last->role);
    }

    #[Test]
    public function runEndedByModelIsComplete(): void
    {
        $client = new ScriptedNoAnswerSubagentClient();
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->tool()])));

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "parent task")]), "system");

        $this->assertTrue($run->isComplete);
        $this->assertSame("parent done", $run->messages->last->content);
    }

    #[Test]
    public function exhaustedSubagentIsReportedAsIncompleteRatherThanAsItsIntermediateText(): void
    {
        $client = new ScriptedExhaustedSubagentClient();
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->tool()])));

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "parent task")]), "system");

        $this->assertSame("The subagent ran out of iterations before finishing. Narrow the task and try again.", $run->messages[1]->content);
        $this->assertTrue($run->messages[1]->isError);
        $this->assertTrue($run->isComplete);
    }

    #[Test]
    public function subagentCalledWithoutTaskIsFlaggedAsToolError(): void
    {
        $client = new ScriptedTasklessSubagentClient();
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->tool()])));

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "parent task")]), "system");

        $this->assertSame("Subagent task is required.", $run->messages[1]->content);
        $this->assertTrue($run->messages[1]->isError);
    }

    #[Test]
    public function everyRunStopReasonHasSubagentFailureGuidance(): void
    {
        $loop = new ReActLLMAgentLoop();
        $method = new ReflectionMethod($loop, "subagentFailureText");

        foreach (LLMRunStopReason::cases() as $stopReason) {
            $run = new LLMRun(new ArrayClass([new LLMMessage(LLMMessageRole::assistant, "partial")]), stopReason: $stopReason);
            $this->assertNotSame("", $method->invoke($loop, $run), "Missing subagent guidance for $stopReason->value.");
        }
    }

    #[Test]
    public function repeatedCallIsServedFromCacheWhateverOrderTheArgumentsArriveIn(): void
    {
        $client = new ScriptedReorderedArgumentsClient();
        LLMAgentCountingTool::$calls = 0;
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->countingTool()])), 25, false);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(1, LLMAgentCountingTool::$calls);
        $this->assertSame("counted 1", $run->messages[1]->content);
        $this->assertStringStartsWith("Do not call this tool with these arguments again", (string)$run->messages[3]->content);
        $this->assertStringContainsString("counted 1", (string)$run->messages[3]->content);
        $this->assertFalse($run->messages[3]->isError);
    }

    /**
     * A read-only tool that declines the cache is executed on every call.
     *
     * Caching asks `isCacheable`, not `isReadOnly`: a tool reading something that moves on its own
     * writes nothing and still answers differently to the same arguments, and serving the second call
     * from the cache would report the first answer as the current one for the rest of the run.
     */
    #[Test]
    public function aReadOnlyToolThatDeclinesTheCacheRunsEveryTime(): void
    {
        $client = new ScriptedReorderedArgumentsClient();
        LLMAgentMovingTool::$calls = 0;
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->movingTool()])), 25, false);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(2, LLMAgentMovingTool::$calls, "The repeated call must reach the tool again.");
        $this->assertSame("moved 2", $run->messages[3]->content);
    }

    #[Test]
    public function callsDifferingInArgumentValuesAreNotConfused(): void
    {
        $client = new ScriptedDistinctArgumentsClient();
        LLMAgentCountingTool::$calls = 0;
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->countingTool()])), 25, false);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(2, LLMAgentCountingTool::$calls);
        $this->assertSame("counted 2", $run->messages[3]->content);
    }

    #[Test]
    public function providerFailureEndsTheRunWithWhatWasAlreadyPaidFor(): void
    {
        $client = new ScriptedFailingClient();
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->tool()])), 25, false);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(LLMRunStopReason::providerFailure, $run->stopReason);
        $this->assertFalse($run->isComplete);
        $this->assertSame(2, $run->messages->count);
        $this->assertSame(9, $run->inputTokens);
        $this->assertSame(4, $run->outputTokens);
        $this->assertTrue($run->isRetryable);
    }

    /** The stop reason says the provider failed; whether that is worth another attempt is the separate question `$isRetryable` answers. */
    #[Test]
    public function aProviderRefusalIsReportedAsNotRetryable(): void
    {
        $agent = new LLMAgent(new ScriptedRefusingClient(), new ToolRegistry(new ArrayClass([$this->tool()])), 25, false);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(LLMRunStopReason::providerFailure, $run->stopReason);
        $this->assertFalse($run->isRetryable);
    }

    /** A run the model concluded has nothing to retry, which is not the same as a retry that would fail. */
    #[Test]
    public function aConcludedRunLeavesRetryabilityUnanswered(): void
    {
        $client = new ScriptedNoAnswerSubagentClient();
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->tool()])));

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "parent task")]), "system");

        $this->assertSame(LLMRunStopReason::done, $run->stopReason);
        $this->assertNull($run->isRetryable);
    }

    /**
     * A fault in the code that talks to the provider is not a provider failure, and must not be reported as one.
     *
     * Both used to surface as `InternalInconsistencyException`, so the catch that ends the run on a provider failure also swallowed a deserialization bug: the run came back partial and retryable, and the caller retried something no amount of retrying would fix. It propagates now.
     */
    #[Test]
    public function programFaultPropagatesInsteadOfEndingTheRunAsAProviderFailure(): void
    {
        $agent = new LLMAgent(new ScriptedFaultyClient(), new ToolRegistry(new ArrayClass([$this->tool()])), 25, false);

        $this->expectException(InternalInconsistencyException::class);
        $this->expectExceptionMessage("Unexpected shape in the decoded body.");

        $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));
    }

    #[Test]
    public function runStoppedByIterationCapSaysSo(): void
    {
        $client = new ScriptedNeverDoneClient();
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->tool()])), 2, false);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(LLMRunStopReason::iterationCap, $run->stopReason);
    }

    #[Test]
    public function runEndedByTheModelSaysSo(): void
    {
        $client = new ScriptedNoAnswerSubagentClient();
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->tool()])));

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "parent task")]), "system");

        $this->assertSame(LLMRunStopReason::done, $run->stopReason);
    }

    #[Test]
    public function outputLimitedTurnIsNotReportedAsACompletedRun(): void
    {
        $agent = new LLMAgent(new ScriptedTerminalTurnClient(LLMTurnStopReason::outputLimit), new ToolRegistry(new ArrayClass()), canSpawnSubagents: false);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(LLMRunStopReason::outputLimit, $run->stopReason);
        $this->assertFalse($run->isComplete);
        $this->assertTrue($run->isRetryable);
    }

    #[Test]
    public function refusedTurnIsNotReportedAsACompletedRun(): void
    {
        $agent = new LLMAgent(new ScriptedTerminalTurnClient(LLMTurnStopReason::refusal), new ToolRegistry(new ArrayClass()), canSpawnSubagents: false);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(LLMRunStopReason::refusal, $run->stopReason);
        $this->assertFalse($run->isComplete);
        $this->assertFalse($run->isRetryable);
    }

    #[Test]
    public function subagentProviderFailureIsReportedAsRetryable(): void
    {
        $client = new ScriptedFailingSubagentClient();
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->tool()])));

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "parent task")]), "system");

        $this->assertSame("The subagent could not reach the model provider. Retrying may work.", $run->messages[1]->content);
        $this->assertTrue($run->messages[1]->isError);
    }

    /** A failure the provider will repeat must not be reported to the model as worth retrying, or it spends the parent's iterations on attempts that answer identically. */
    #[Test]
    public function subagentProviderRefusalIsReportedAsNotWorthRetrying(): void
    {
        $client = new ScriptedRefusedSubagentClient();
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->tool()])));

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "parent task")]), "system");

        $this->assertStringContainsString("the failure will repeat", (string)$run->messages[1]->content);
        $this->assertTrue($run->messages[1]->isError);
    }

    #[Test]
    public function aRunPastItsTimeLimitStopsBeforeSpendingAnotherTurn(): void
    {
        $client = new ScriptedNeverDoneClient();
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->tool()])), 25, false, 0.0);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(LLMRunStopReason::deadline, $run->stopReason);
        $this->assertFalse($run->isComplete);
        $this->assertSame(0, $client->calls);
    }

    #[Test]
    public function aRunWithinItsTimeLimitIsLeftAlone(): void
    {
        $client = new ScriptedNoAnswerSubagentClient();
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->tool()])), 25, true, 60.0);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "parent task")]), "system");

        $this->assertSame(LLMRunStopReason::done, $run->stopReason);
        $this->assertTrue($run->isComplete);
    }

    #[Test]
    public function noTimeLimitLeavesTheLoopBoundedOnlyByIterations(): void
    {
        $client = new ScriptedNeverDoneClient();
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->tool()])), 3, false);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(LLMRunStopReason::iterationCap, $run->stopReason);
        $this->assertSame(3, $client->calls);
    }

    #[Test]
    public function aRepeatedCallToAWritingToolIsExecutedAgainRatherThanCached(): void
    {
        $client = new ScriptedReorderedArgumentsClient("writing_tool");
        LLMAgentWritingTool::$calls = 0;
        $policy = new LLMExecutionPolicy(writeApproval: static fn(LLMToolCall $call): bool => $call->name === "writing_tool");
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->writingTool()])), 25, false, executionPolicy: $policy);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(2, LLMAgentWritingTool::$calls);
        $this->assertSame("wrote 2", $run->messages[3]->content);
    }

    #[Test]
    public function aWritingToolWithoutApprovalIsNotExecuted(): void
    {
        $client = new ScriptedReorderedArgumentsClient("writing_tool");
        LLMAgentWritingTool::$calls = 0;
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->writingTool()])), canSpawnSubagents: false);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(0, LLMAgentWritingTool::$calls);
        $this->assertSame(LLMRunStopReason::writeApprovalRequired, $run->stopReason);
        $this->assertTrue($run->messages->last->isError);
    }

    #[Test]
    public function toolCallBudgetStopsBeforeAnotherToolRuns(): void
    {
        $client = new ScriptedReorderedArgumentsClient();
        LLMAgentCountingTool::$calls = 0;
        $policy = new LLMExecutionPolicy(maxToolCalls: 1);
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->countingTool()])), canSpawnSubagents: false, executionPolicy: $policy);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(1, LLMAgentCountingTool::$calls);
        $this->assertSame(LLMRunStopReason::toolCallLimit, $run->stopReason);
        $this->assertTrue($run->messages->last->isError);
    }

    #[Test]
    public function subagentBudgetStopsBeforeLaunchingOne(): void
    {
        $client = new ScriptedSubagentClient();
        $policy = new LLMExecutionPolicy(maxSubagentCalls: 0);
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->tool()])), executionPolicy: $policy);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(LLMRunStopReason::subagentCallLimit, $run->stopReason);
        $this->assertCount(1, $client->toolNamesByCall);
    }

    #[Test]
    public function tokenBudgetCountsParentAndSubagentTogether(): void
    {
        $client = new ScriptedSubagentClient();
        $policy = new LLMExecutionPolicy(maxTotalTokens: 18);
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass([$this->tool()])), executionPolicy: $policy);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(LLMRunStopReason::totalTokenLimit, $run->stopReason);
        $this->assertSame(17, $run->inputTokens);
        $this->assertSame(5, $run->outputTokens);
    }

    #[Test]
    public function providerTurnThatExceedsItsInputBudgetIsIncomplete(): void
    {
        $policy = new LLMExecutionPolicy(maxInputTokens: 2);
        $agent = new LLMAgent(new ScriptedTerminalTurnClient(LLMTurnStopReason::completed), new ToolRegistry(new ArrayClass()), canSpawnSubagents: false, executionPolicy: $policy);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(LLMRunStopReason::inputTokenLimit, $run->stopReason);
        $this->assertFalse($run->isComplete);
    }

    #[Test]
    public function contextThatCannotBeSafelyTruncatedStopsBeforeCallingTheProvider(): void
    {
        $assembler = new WindowedLLMContextAssembler(maximumMessages: 0);
        $agent = new LLMAgent(new ScriptedTerminalTurnClient(LLMTurnStopReason::completed), new ToolRegistry(new ArrayClass()), canSpawnSubagents: false, contextAssembler: $assembler);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(LLMRunStopReason::contextLimit, $run->stopReason);
        $this->assertFalse($run->isComplete);
        $this->assertFalse($run->isRetryable);
        $this->assertTrue($run->messages->isEmpty);
    }

    private function writingTool(): AbstractTool
    {
        /** @var AbstractTool */
        return new ReflectionClass(LLMAgentWritingTool::class)->newInstanceWithoutConstructor();
    }

    private function tool(): AbstractTool
    {
        /** @var AbstractTool */
        return new ReflectionClass(LLMAgentProbeTool::class)->newInstanceWithoutConstructor();
    }

    private function countingTool(): AbstractTool
    {
        /** @var AbstractTool */
        return new ReflectionClass(LLMAgentCountingTool::class)->newInstanceWithoutConstructor();
    }

    private function movingTool(): AbstractTool
    {
        /** @var AbstractTool */
        return new ReflectionClass(LLMAgentMovingTool::class)->newInstanceWithoutConstructor();
    }
}

final class RecordingAgentLoop implements LLMAgentLoop
{
    public string $query = "";
    public ?string $systemPrompt = null;
    public int $maxIterations = 0;
    public bool $canSpawnSubagents = true;
    /** @var array<string> */
    public array $toolNames = [];

    #[Override]
    public function run(LLMAgentRuntime $runtime): LLMRun
    {
        $messages = $runtime->messages;
        $this->query = (string)$messages->first->content;
        $this->systemPrompt = $runtime->systemPrompt;
        $this->maxIterations = $runtime->maxIterations;
        $this->canSpawnSubagents = $runtime->canSpawnSubagents;
        $this->toolNames = $runtime->tools->map(fn(ToolDescriptor $tool): string => $tool->name)->array;
        $messages->append(new LLMMessage(LLMMessageRole::assistant, "mutated snapshot"));
        $runtime->completeTurn($runtime->tools);
        return $runtime->finish();
    }
}

final class RuntimeToolUsingLoop implements LLMAgentLoop
{
    #[Override]
    public function run(LLMAgentRuntime $runtime): LLMRun
    {
        while (true) {
            $turn = $runtime->completeTurn($runtime->tools);
            if ($turn->stopReason !== LLMTurnStopReason::toolUse) {
                return $runtime->finish();
            }
            foreach ($turn->toolCalls as $toolCall) {
                $runtime->executeTool($toolCall);
            }
        }
    }
}

final class ScriptedTerminalTurnClient extends LLMClient
{
    #[Override]
    public string $version {
        get => "test";
    }
    #[Override]
    public int $maxTokens {
        get => 1024;
    }

    public function __construct(private readonly LLMTurnStopReason $turnStopReason)
    {
        parent::__construct();
    }

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null, ?LLMExecutionDeadline $deadline = null): LLMTurn
    {
        return new LLMTurn("partial", new ArrayClass(), 3, 4, stopReason: $this->turnStopReason);
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

final class ScriptedSubagentClient extends LLMClient
{
    #[Override]
    public string $version {
        get => "test";
    }
    #[Override]
    public int $maxTokens {
        get => 1024;
    }

    /** @var list<list<string>> */
    public array $toolNamesByCall = [];
    /** @var list<string|null> */
    public array $systemPromptsByCall = [];
    /** @var array<string, mixed> */
    public array $subagentInputSchema = [];
    private int $call = 0;

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null, ?LLMExecutionDeadline $deadline = null): LLMTurn
    {
        $this->toolNamesByCall[] = $tools->map(fn(ToolDescriptor $tool): string => $tool->name)->array;
        $this->systemPromptsByCall[] = $systemPrompt;
        foreach ($tools as $tool) {
            if ($tool->name === "run_subagent") {
                $this->subagentInputSchema = $tool->inputSchema;
            }
        }
        return match ($this->call++) {
            0 => new LLMTurn(null, new ArrayClass([new LLMToolCall("parent-subagent", "run_subagent", new Dictionary(["task" => "inspect one thing", "systemPrompt" => "replace the parent policy"]))]), 10, 2),
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

final class ScriptedNoAnswerSubagentClient extends LLMClient
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
            0 => new LLMTurn(null, new ArrayClass([new LLMToolCall("parent-subagent", "run_subagent", new Dictionary(["task" => "inspect one thing"]))]), 10, 2),
            1 => new LLMTurn(null, new ArrayClass(), 7, 3),
            2 => new LLMTurn("parent done", new ArrayClass(), 6, 1),
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

/** Never concludes: every turn asks for a tool again, so only the iteration cap can stop the loop. */
final class ScriptedNeverDoneClient extends LLMClient
{
    #[Override]
    public string $version {
        get => "test";
    }
    #[Override]
    public int $maxTokens {
        get => 1024;
    }

    public int $calls = 0;

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     * @noinspection PhpUnnecessaryCurlyVarSyntaxInspection
     */
    #[Override]
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null, ?LLMExecutionDeadline $deadline = null): LLMTurn
    {
        $this->calls++;
        return new LLMTurn(null, new ArrayClass([new LLMToolCall("call-{$this->calls}", "probe_tool", new Dictionary(["n" => $this->calls]))]));
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

/**
 * The subagent emits text on its first turn and then never concludes, exhausting its cap. The
 * parent must report the cap rather than pass that intermediate text off as the answer.
 */
final class ScriptedExhaustedSubagentClient extends LLMClient
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
        $call = $this->call++;
        if ($call === 0) {
            return new LLMTurn(null, new ArrayClass([new LLMToolCall("parent-subagent", "run_subagent", new Dictionary(["task" => "inspect one thing"]))]));
        }
        if ($call === 1) {
            return new LLMTurn("partial finding, still working", new ArrayClass([new LLMToolCall("sub-1", "probe_tool", new Dictionary(["n" => 1]))]));
        }
        if ($call <= 8) {
            return new LLMTurn(null, new ArrayClass([new LLMToolCall("sub-$call", "probe_tool", new Dictionary(["n" => $call]))]));
        }
        return new LLMTurn("parent done", new ArrayClass());
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

/** Calls the subagent tool with no `task`, the mis-call the parent must flag back to the model. */
final class ScriptedTasklessSubagentClient extends LLMClient
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
            0 => new LLMTurn(null, new ArrayClass([new LLMToolCall("parent-subagent", "run_subagent", new Dictionary(["task" => "   "]))])),
            1 => new LLMTurn("parent done", new ArrayClass()),
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

/** Issues the same call twice with the argument order swapped; only the first should reach the tool. */
final class ScriptedReorderedArgumentsClient extends LLMClient
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

    public function __construct(private readonly string $toolName = "counting_tool")
    {
        parent::__construct();
    }

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null, ?LLMExecutionDeadline $deadline = null): LLMTurn
    {
        return match ($this->call++) {
            0 => new LLMTurn(null, new ArrayClass([new LLMToolCall("c1", $this->toolName, new Dictionary(["a" => 1, "b" => "two"]))])),
            1 => new LLMTurn(null, new ArrayClass([new LLMToolCall("c2", $this->toolName, new Dictionary(["b" => "two", "a" => 1]))])),
            2 => new LLMTurn("done", new ArrayClass()),
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

/** Same keys, different values: two genuinely distinct calls that must both reach the tool. */
final class ScriptedDistinctArgumentsClient extends LLMClient
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
            0 => new LLMTurn(null, new ArrayClass([new LLMToolCall("c1", "counting_tool", new Dictionary(["a" => 1, "b" => "two"]))])),
            1 => new LLMTurn(null, new ArrayClass([new LLMToolCall("c2", "counting_tool", new Dictionary(["a" => 2, "b" => "two"]))])),
            2 => new LLMTurn("done", new ArrayClass()),
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

/**
 * Counts how many times it actually executed, so a cache hit is observable.
 *
 * The counter is static because `AbstractTool`'s constructor needs a Core Data context this
 * test has no use for, so instances are built with `newInstanceWithoutConstructor()` and
 * cannot initialise instance state. Reset it in the test's arrange step.
 */
/** Read-only, like a clock: it writes nothing, and answers differently to the same arguments. */
final class LLMAgentMovingTool extends AbstractTool
{
    public static int $calls = 0;

    #[Override]
    public string $name {
        get => "counting_tool";
    }
    #[Override]
    public bool $isReadOnly {
        get => true;
    }
    #[Override]
    public bool $isCacheable {
        get => false;
    }
    #[Override]
    public string $description {
        get => "Moving tool.";
    }
    #[Override]
    public array $inputSchema {
        get => ["type" => "object"];
    }

    /** @return ArrayClass<ContentItem> */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        self::$calls++;
        return new ArrayClass([new ContentItem("text", "moved " . self::$calls)]);
    }
}

final class LLMAgentCountingTool extends AbstractTool
{
    public static int $calls = 0;

    #[Override]
    public string $name {
        get => "counting_tool";
    }
    #[Override]
    public bool $isReadOnly {
        get => true;
    }
    #[Override]
    public string $description {
        get => "Counting tool.";
    }
    #[Override]
    public array $inputSchema {
        get => ["type" => "object"];
    }

    /** @return ArrayClass<ContentItem> */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        self::$calls++;
        return new ArrayClass([new ContentItem("text", "counted " . self::$calls)]);
    }
}

/** Writes, so a repeated call must reach it every time rather than being served from the run's cache. */
final class LLMAgentWritingTool extends AbstractTool
{
    public static int $calls = 0;

    #[Override]
    public string $name {
        get => "writing_tool";
    }
    #[Override]
    public string $description {
        get => "Writing tool.";
    }
    #[Override]
    public array $inputSchema {
        get => ["type" => "object"];
    }

    /** @return ArrayClass<ContentItem> */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        self::$calls++;
        return new ArrayClass([new ContentItem("text", "wrote " . self::$calls)]);
    }
}

final class LLMAgentProbeTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "probe_tool";
    }
    #[Override]
    public string $description {
        get => "Probe tool.";
    }
    #[Override]
    public bool $isReadOnly {
        get => true;
    }
    #[Override]
    public array $inputSchema {
        get => ["type" => "object"];
    }

    /** @return ArrayClass<ContentItem> */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        return new ArrayClass([new ContentItem("text", "probe result")]);
    }
}

/** Fails on its second turn the way a bug in a client's own deserialization does, rather than the way a provider does. */
final class ScriptedFaultyClient extends LLMClient
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
        if ($this->call++ === 0) {
            return new LLMTurn(null, new ArrayClass([new LLMToolCall("c1", "probe_tool", new Dictionary())]), 9, 4);
        }
        throw new InternalInconsistencyException("Unexpected shape in the decoded body.");
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

/** Fails definitively on its second turn, the way a rejected key or an unknown model does. */
final class ScriptedRefusingClient extends LLMClient
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
        if ($this->call++ === 0) {
            return new LLMTurn(null, new ArrayClass([new LLMToolCall("c1", "probe_tool", new Dictionary())]), 9, 4);
        }
        throw new LLMProviderException("The LLM provider returned HTTP 401");
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

/** Fails on its second turn, after one turn has already been paid for and recorded. */
final class ScriptedFailingClient extends LLMClient
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
        if ($this->call++ === 0) {
            return new LLMTurn(null, new ArrayClass([new LLMToolCall("c1", "probe_tool", new Dictionary())]), 9, 4);
        }
        throw new LLMProviderException("The LLM provider returned HTTP 503", true);
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

/** The parent delegates to a subagent whose own first turn hits a provider failure. */
/** Fails the sub-run the way a rejected key does: definitively, so another attempt would answer the same. */
final class ScriptedRefusedSubagentClient extends LLMClient
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
            0 => new LLMTurn(null, new ArrayClass([new LLMToolCall("parent-subagent", "run_subagent", new Dictionary(["task" => "inspect one thing"]))])),
            1 => throw new LLMProviderException("The LLM provider returned HTTP 401"),
            default => new LLMTurn("parent done", new ArrayClass()),
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

final class ScriptedFailingSubagentClient extends LLMClient
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
            0 => new LLMTurn(null, new ArrayClass([new LLMToolCall("parent-subagent", "run_subagent", new Dictionary(["task" => "inspect one thing"]))])),
            1 => throw new LLMProviderException("The LLM provider returned HTTP 502", true),
            default => new LLMTurn("parent done", new ArrayClass()),
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
