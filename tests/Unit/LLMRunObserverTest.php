<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use LogicException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Service\LLM\LLMAgent;
use Sabatier\Service\LLM\LLMClient;
use Sabatier\Service\LLM\LLMClock;
use Sabatier\Service\LLM\LLMContext;
use Sabatier\Service\LLM\LLMContextAssembler;
use Sabatier\Service\LLM\LLMContextAssemblyFailedEvent;
use Sabatier\Service\LLM\LLMContextAssemblyFinishedEvent;
use Sabatier\Service\LLM\LLMContextAssemblyStartedEvent;
use Sabatier\Service\LLM\LLMExecutionPolicy;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;
use Sabatier\Service\LLM\LLMModelTurnFailedEvent;
use Sabatier\Service\LLM\LLMModelTurnFinishedEvent;
use Sabatier\Service\LLM\LLMModelTurnStartedEvent;
use Sabatier\Service\LLM\LLMProviderException;
use Sabatier\Service\LLM\LLMRunEvent;
use Sabatier\Service\LLM\LLMRunFailedEvent;
use Sabatier\Service\LLM\LLMRunFinishedEvent;
use Sabatier\Service\LLM\LLMRunObserver;
use Sabatier\Service\LLM\LLMRunObserverFailurePolicy;
use Sabatier\Service\LLM\LLMRunStartedEvent;
use Sabatier\Service\LLM\LLMRunStopReason;
use Sabatier\Service\LLM\LLMToolCall;
use Sabatier\Service\LLM\LLMToolCallDisposition;
use Sabatier\Service\LLM\LLMToolCallFinishedEvent;
use Sabatier\Service\LLM\LLMToolCallStartedEvent;
use Sabatier\Service\LLM\LLMTurn;
use Sabatier\Service\LLM\WindowedLLMContextAssembler;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\ToolRegistry;

final class LLMRunObserverTest extends TestCase
{
    #[Test]
    public function observerSeesEveryBoundaryOfASingleRunInOrder(): void
    {
        $observer = new RecordingRunObserver();
        $agent = new LLMAgent(new ScriptedObservedClient(), new ToolRegistry(new ArrayClass([$this->tool()])), canSpawnSubagents: false, observer: $observer);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]), "system");

        $this->assertSame(LLMRunStopReason::done, $run->stopReason);
        $this->assertSame([
            LLMRunStartedEvent::class,
            LLMContextAssemblyStartedEvent::class,
            LLMContextAssemblyFinishedEvent::class,
            LLMModelTurnStartedEvent::class,
            LLMModelTurnFinishedEvent::class,
            LLMToolCallStartedEvent::class,
            LLMToolCallFinishedEvent::class,
            LLMContextAssemblyStartedEvent::class,
            LLMContextAssemblyFinishedEvent::class,
            LLMModelTurnStartedEvent::class,
            LLMModelTurnFinishedEvent::class,
            LLMRunFinishedEvent::class,
        ], array_map(fn(LLMRunEvent $event): string => $event::class, $observer->events));
    }

    #[Test]
    public function eventsCarryScalarSnapshotsInsteadOfLiveLoopObjects(): void
    {
        $observer = new RecordingRunObserver();
        $agent = new LLMAgent(new ScriptedObservedClient(), new ToolRegistry(new ArrayClass([$this->tool()])), canSpawnSubagents: false, observer: $observer);

        $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]), "system");

        /** @var LLMRunStartedEvent $started */
        $started = $observer->eventsOf(LLMRunStartedEvent::class)[0];
        /** @var LLMContextAssemblyStartedEvent $contextStarted */
        $contextStarted = $observer->eventsOf(LLMContextAssemblyStartedEvent::class)[0];
        /** @var LLMContextAssemblyFinishedEvent $contextFinished */
        $contextFinished = $observer->eventsOf(LLMContextAssemblyFinishedEvent::class)[0];
        /** @var LLMModelTurnStartedEvent $firstTurn */
        $firstTurn = $observer->eventsOf(LLMModelTurnStartedEvent::class)[0];
        /** @var LLMModelTurnFinishedEvent $finishedTurn */
        $finishedTurn = $observer->eventsOf(LLMModelTurnFinishedEvent::class)[0];
        $this->assertSame(1, $started->messageCount);
        $this->assertTrue($started->hasSystemPrompt);
        $this->assertSame(1, $contextStarted->iteration);
        $this->assertSame(1, $contextStarted->messageCount);
        $this->assertSame(1, $contextFinished->messageCount);
        $this->assertSame(0, $contextFinished->omittedMessageCount);
        $this->assertFalse($contextFinished->wasCompacted);
        $this->assertTrue($contextFinished->isWithinLimit);
        $this->assertGreaterThanOrEqual(0.0, $contextFinished->duration);
        $this->assertSame(1, $firstTurn->messageCount);
        $this->assertSame(0, $firstTurn->omittedMessageCount);
        $this->assertFalse($firstTurn->wasCompacted);
        $this->assertSame(5, $finishedTurn->inputTokens);
        $this->assertSame(2, $finishedTurn->outputTokens);
        $this->assertSame(1, $finishedTurn->toolCallCount);
        $this->assertGreaterThanOrEqual(0.0, $finishedTurn->duration);
    }

    #[Test]
    public function toolOutcomesDistinguishExecutionFromCache(): void
    {
        $observer = new RecordingRunObserver();
        $agent = new LLMAgent(new ScriptedRepeatingClient(), new ToolRegistry(new ArrayClass([$this->tool()])), canSpawnSubagents: false, observer: $observer);

        $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]), null);

        /** @var list<LLMToolCallFinishedEvent> $events */
        $events = $observer->eventsOf(LLMToolCallFinishedEvent::class);
        $this->assertSame(LLMToolCallDisposition::executed, $events[0]->disposition);
        $this->assertSame(LLMToolCallDisposition::cached, $events[1]->disposition);
        $this->assertGreaterThanOrEqual(0.0, $events[0]->duration);
        $this->assertSame(0.0, $events[1]->duration);
    }

    #[Test]
    public function injectedClockMakesEventTimestampsAndDurationsDeterministic(): void
    {
        $observer = new RecordingRunObserver();
        $agent = new LLMAgent(new ScriptedObservedClient(), new ToolRegistry(new ArrayClass([$this->tool()])), canSpawnSubagents: false, observer: $observer, clock: new AdvancingRunClock());

        $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        /** @var LLMModelTurnFinishedEvent $turn */
        $turn = $observer->eventsOf(LLMModelTurnFinishedEvent::class)[0];
        /** @var LLMContextAssemblyFinishedEvent $context */
        $context = $observer->eventsOf(LLMContextAssemblyFinishedEvent::class)[0];
        /** @var LLMToolCallFinishedEvent $tool */
        $tool = $observer->eventsOf(LLMToolCallFinishedEvent::class)[0];
        $this->assertSame(1700000000.0, $observer->events[0]->timestamp);
        $this->assertSame(0.25, $context->duration);
        $this->assertSame(0.25, $turn->duration);
        $this->assertSame(0.25, $tool->duration);
    }

    #[Test]
    public function contextLimitClosesAssemblyWithoutOpeningAModelTurn(): void
    {
        $observer = new RecordingRunObserver();
        $assembler = new WindowedLLMContextAssembler(maximumMessages: 0);
        $agent = new LLMAgent(new ScriptedObservedClient(), new ToolRegistry(new ArrayClass()), canSpawnSubagents: false, contextAssembler: $assembler, observer: $observer);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        /** @var LLMContextAssemblyFinishedEvent $event */
        $event = $observer->eventsOf(LLMContextAssemblyFinishedEvent::class)[0];
        $this->assertSame(LLMRunStopReason::contextLimit, $run->stopReason);
        $this->assertFalse($event->isWithinLimit);
        $this->assertCount(0, $observer->eventsOf(LLMModelTurnStartedEvent::class));
        $this->assertCount(1, $observer->eventsOf(LLMRunFinishedEvent::class));
    }

    #[Test]
    public function contextFailureClosesAssemblyAndRunSpans(): void
    {
        $observer = new RecordingRunObserver();
        $agent = new LLMAgent(new ScriptedObservedClient(), new ToolRegistry(new ArrayClass()), canSpawnSubagents: false, contextAssembler: new FailingObservedContextAssembler(), observer: $observer);

        try {
            $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));
            $this->fail("The context failure should escape the run.");
        } catch (LogicException $exception) {
            $this->assertSame("context exploded", $exception->getMessage());
        }

        /** @var LLMContextAssemblyFailedEvent $contextEvent */
        $contextEvent = $observer->eventsOf(LLMContextAssemblyFailedEvent::class)[0];
        /** @var LLMRunFailedEvent $runEvent */
        $runEvent = $observer->eventsOf(LLMRunFailedEvent::class)[0];
        $this->assertSame(1, $contextEvent->iteration);
        $this->assertSame(LogicException::class, $contextEvent->errorType);
        $this->assertSame("context exploded", $contextEvent->errorMessage);
        $this->assertGreaterThanOrEqual(0.0, $contextEvent->duration);
        $this->assertSame(LogicException::class, $runEvent->errorType);
        $this->assertCount(0, $observer->eventsOf(LLMModelTurnStartedEvent::class));
    }

    #[Test]
    public function subagentReportsUnderItsOwnIdentifierNamingItsParent(): void
    {
        $observer = new RecordingRunObserver();
        $agent = new LLMAgent(new ScriptedObservedSubagentClient(), new ToolRegistry(new ArrayClass([$this->tool()])), observer: $observer);

        $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "parent task")]), "system");

        /** @var list<LLMRunStartedEvent> $events */
        $events = $observer->eventsOf(LLMRunStartedEvent::class);
        $parent = $events[0]->context;
        $child = $events[1]->context;
        $this->assertNull($parent->parentIdentifier);
        $this->assertFalse($parent->isSubagent);
        $this->assertSame(0, $parent->depth);
        $this->assertSame($parent->identifier, $child->parentIdentifier);
        $this->assertTrue($child->isSubagent);
        $this->assertSame(1, $child->depth);
        $this->assertNotSame($parent->identifier, $child->identifier);
    }

    #[Test]
    public function bothRunsReportTheirOwnEnding(): void
    {
        $observer = new RecordingRunObserver();
        $agent = new LLMAgent(new ScriptedObservedSubagentClient(), new ToolRegistry(new ArrayClass([$this->tool()])), observer: $observer);

        $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "parent task")]), "system");

        /** @var list<LLMRunFinishedEvent> $events */
        $events = $observer->eventsOf(LLMRunFinishedEvent::class);
        $this->assertCount(2, $events);
        $this->assertTrue($events[0]->context->isSubagent);
        $this->assertFalse($events[1]->context->isSubagent);
        $this->assertSame(LLMRunStopReason::done, $events[0]->stopReason);
        $this->assertSame(LLMRunStopReason::done, $events[1]->stopReason);
    }

    #[Test]
    public function budgetRejectedToolCallReportsItsOwnOutcomeAndRunEnding(): void
    {
        $observer = new RecordingRunObserver();
        $policy = new LLMExecutionPolicy(maxToolCalls: 0);
        $agent = new LLMAgent(new ScriptedObservedClient(), new ToolRegistry(new ArrayClass([$this->tool()])), canSpawnSubagents: false, executionPolicy: $policy, observer: $observer);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]), null);

        /** @var LLMToolCallFinishedEvent $toolEvent */
        $toolEvent = $observer->eventsOf(LLMToolCallFinishedEvent::class)[0];
        /** @var LLMRunFinishedEvent $runEvent */
        $runEvent = $observer->eventsOf(LLMRunFinishedEvent::class)[0];
        $this->assertSame(LLMRunStopReason::toolCallLimit, $run->stopReason);
        $this->assertSame(LLMToolCallDisposition::budgetExceeded, $toolEvent->disposition);
        $this->assertSame(LLMRunStopReason::toolCallLimit, $runEvent->stopReason);
    }

    #[Test]
    public function deniedWriteReportsItsOwnOutcome(): void
    {
        $observer = new RecordingRunObserver();
        $agent = new LLMAgent(new ScriptedObservedWritingClient(), new ToolRegistry(new ArrayClass([$this->writingTool()])), canSpawnSubagents: false, observer: $observer);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        /** @var LLMToolCallFinishedEvent $event */
        $event = $observer->eventsOf(LLMToolCallFinishedEvent::class)[0];
        $this->assertSame(LLMRunStopReason::writeApprovalRequired, $run->stopReason);
        $this->assertSame(LLMToolCallDisposition::denied, $event->disposition);
        $this->assertGreaterThanOrEqual(0.0, $event->duration);
    }

    #[Test]
    public function providerFailureClosesTheTurnAndReturnsANormalFailedRun(): void
    {
        $observer = new RecordingRunObserver();
        $agent = new LLMAgent(new ScriptedObservedProviderFailureClient(), new ToolRegistry(new ArrayClass()), canSpawnSubagents: false, observer: $observer);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame(LLMRunStopReason::providerFailure, $run->stopReason);
        $this->assertCount(1, $observer->eventsOf(LLMModelTurnFailedEvent::class));
        $this->assertCount(1, $observer->eventsOf(LLMRunFinishedEvent::class));
        $this->assertCount(0, $observer->eventsOf(LLMRunFailedEvent::class));
    }

    #[Test]
    public function unexpectedToolFailureClosesTheToolAndTheRun(): void
    {
        $observer = new RecordingRunObserver();
        $agent = new LLMAgent(new ScriptedObservedExplodingToolClient(), new ToolRegistry(new ArrayClass([$this->explodingTool()])), canSpawnSubagents: false, observer: $observer);

        try {
            $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));
            $this->fail("The tool failure should escape the run.");
        } catch (LogicException $exception) {
            $this->assertSame("tool exploded", $exception->getMessage());
        }

        /** @var LLMToolCallFinishedEvent $toolEvent */
        $toolEvent = $observer->eventsOf(LLMToolCallFinishedEvent::class)[0];
        /** @var LLMRunFailedEvent $runEvent */
        $runEvent = $observer->eventsOf(LLMRunFailedEvent::class)[0];
        $this->assertSame(LLMToolCallDisposition::failed, $toolEvent->disposition);
        $this->assertSame(LogicException::class, $runEvent->errorType);
    }

    #[Test]
    public function bestEffortObserverFailureDoesNotChangeTheRun(): void
    {
        $observer = new FailingOnceRunObserver();
        $agent = new LLMAgent(new ScriptedObservedClient(), new ToolRegistry(new ArrayClass([$this->tool()])), canSpawnSubagents: false, observer: $observer);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertTrue($run->isComplete);
        $this->assertCount(1, $observer->eventsOf(LLMRunFinishedEvent::class));
    }

    #[Test]
    public function strictObserverFailureAbortsAndEmitsAFailedRunBestEffort(): void
    {
        $observer = new FailingOnceRunObserver();
        $agent = new LLMAgent(new ScriptedObservedClient(), new ToolRegistry(new ArrayClass([$this->tool()])), canSpawnSubagents: false, observer: $observer, observerFailurePolicy: LLMRunObserverFailurePolicy::strict);

        try {
            $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));
            $this->fail("Strict observer failure should escape the run.");
        } catch (LogicException $exception) {
            $this->assertSame("observer exploded", $exception->getMessage());
        }

        $this->assertCount(1, $observer->eventsOf(LLMRunFailedEvent::class));
    }

    #[Test]
    public function runWithoutAnObserverBehavesIdentically(): void
    {
        $agent = new LLMAgent(new ScriptedObservedClient(), new ToolRegistry(new ArrayClass([$this->tool()])), canSpawnSubagents: false);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]), "system");

        $this->assertSame(LLMRunStopReason::done, $run->stopReason);
        $this->assertTrue($run->isComplete);
    }

    private function tool(): AbstractTool
    {
        /** @var AbstractTool */
        return new ReflectionClass(ObservedProbeTool::class)->newInstanceWithoutConstructor();
    }

    private function writingTool(): AbstractTool
    {
        /** @var AbstractTool */
        return new ReflectionClass(ObservedWritingTool::class)->newInstanceWithoutConstructor();
    }

    private function explodingTool(): AbstractTool
    {
        /** @var AbstractTool */
        return new ReflectionClass(ObservedExplodingTool::class)->newInstanceWithoutConstructor();
    }
}

final class FailingObservedContextAssembler implements LLMContextAssembler
{
    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    public function assemble(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): LLMContext
    {
        throw new LogicException("context exploded");
    }
}

final class ObservedProbeTool extends AbstractTool
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
        get => ["type" => "object", "properties" => ["a" => ["type" => "integer"]]];
    }

    /**
     * @param Dictionary<mixed> $arguments
     * @return ArrayClass<ContentItem>
     */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        return new ArrayClass([new ContentItem("text", "probe result")]);
    }
}

final class ObservedWritingTool extends AbstractTool
{
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
        get => ["type" => "object", "properties" => []];
    }

    /**
     * @param Dictionary<mixed> $arguments
     * @return ArrayClass<ContentItem>
     */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        return new ArrayClass([new ContentItem("text", "written")]);
    }
}

final class ObservedExplodingTool extends AbstractTool
{
    #[Override]
    public string $name {
        get => "exploding_tool";
    }
    #[Override]
    public string $description {
        get => "Exploding tool.";
    }
    #[Override]
    public bool $isReadOnly {
        get => true;
    }
    #[Override]
    public array $inputSchema {
        get => ["type" => "object", "properties" => []];
    }

    /**
     * @param Dictionary<mixed> $arguments
     * @return ArrayClass<ContentItem>
     */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        throw new LogicException("tool exploded");
    }
}

final class RecordingRunObserver implements LLMRunObserver
{
    /** @var list<LLMRunEvent> */
    public array $events = [];

    #[Override]
    public function observe(LLMRunEvent $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @param class-string<LLMRunEvent> $class
     * @return list<LLMRunEvent>
     */
    public function eventsOf(string $class): array
    {
        return array_values(array_filter($this->events, fn(LLMRunEvent $event): bool => $event instanceof $class));
    }
}

final class FailingOnceRunObserver implements LLMRunObserver
{
    private bool $hasFailed = false;
    private RecordingRunObserver $recording;

    public function __construct()
    {
        $this->recording = new RecordingRunObserver();
    }

    #[Override]
    public function observe(LLMRunEvent $event): void
    {
        if (!$this->hasFailed) {
            $this->hasFailed = true;
            throw new LogicException("observer exploded");
        }
        $this->recording->observe($event);
    }

    /**
     * @param class-string<LLMRunEvent> $class
     * @return list<LLMRunEvent>
     */
    public function eventsOf(string $class): array
    {
        return $this->recording->eventsOf($class);
    }
}

final class AdvancingRunClock implements LLMClock
{
    private float $time = 0.0;

    #[Override]
    public float $timestamp {
        get => 1700000000.0;
    }
    #[Override]
    public float $monotonicTime {
        get {
            $this->time += 0.25;
            return $this->time;
        }
    }
}

abstract class ScriptedObserverClient extends LLMClient
{
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

final class ScriptedObservedClient extends ScriptedObserverClient
{
    private int $call = 0;

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): LLMTurn
    {
        return match ($this->call++) {
            0 => new LLMTurn(null, new ArrayClass([new LLMToolCall("call-1", "probe_tool", new Dictionary())]), 5, 2),
            1 => new LLMTurn("done", new ArrayClass(), 4, 3),
            default => throw new LogicException("Unexpected LLM call."),
        };
    }
}

final class ScriptedObservedWritingClient extends ScriptedObserverClient
{
    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): LLMTurn
    {
        return new LLMTurn(null, new ArrayClass([new LLMToolCall("write-1", "writing_tool", new Dictionary())]));
    }
}

final class ScriptedObservedExplodingToolClient extends ScriptedObserverClient
{
    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): LLMTurn
    {
        return new LLMTurn(null, new ArrayClass([new LLMToolCall("explode-1", "exploding_tool", new Dictionary())]));
    }
}

final class ScriptedObservedProviderFailureClient extends ScriptedObserverClient
{
    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): LLMTurn
    {
        throw new LLMProviderException("provider unavailable", true);
    }
}

final class ScriptedRepeatingClient extends ScriptedObserverClient
{
    private int $call = 0;

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): LLMTurn
    {
        return match ($this->call++) {
            0 => new LLMTurn(null, new ArrayClass([new LLMToolCall("call-1", "probe_tool", new Dictionary(["a" => 1]))]), 5, 2),
            1 => new LLMTurn(null, new ArrayClass([new LLMToolCall("call-2", "probe_tool", new Dictionary(["a" => 1]))]), 5, 2),
            2 => new LLMTurn("done", new ArrayClass(), 4, 3),
            default => throw new LogicException("Unexpected LLM call."),
        };
    }
}

final class ScriptedObservedSubagentClient extends ScriptedObserverClient
{
    private int $call = 0;

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): LLMTurn
    {
        return match ($this->call++) {
            0 => new LLMTurn(null, new ArrayClass([new LLMToolCall("parent-subagent", "run_subagent", new Dictionary(["task" => "inspect one thing"]))]), 10, 2),
            1 => new LLMTurn(null, new ArrayClass([new LLMToolCall("sub-tool", "probe_tool", new Dictionary())]), 7, 3),
            2 => new LLMTurn("sub answer", new ArrayClass(), 5, 4),
            3 => new LLMTurn("parent done", new ArrayClass(), 6, 1),
            default => throw new LogicException("Unexpected LLM call."),
        };
    }
}
