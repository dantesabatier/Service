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
use Sabatier\Service\LLM\LLMContext;
use Sabatier\Service\LLM\LLMExecutionPolicy;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;
use Sabatier\Service\LLM\LLMRun;
use Sabatier\Service\LLM\LLMRunContext;
use Sabatier\Service\LLM\LLMRunObserver;
use Sabatier\Service\LLM\LLMRunStopReason;
use Sabatier\Service\LLM\LLMToolCall;
use Sabatier\Service\LLM\LLMTurn;
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
        $agent = new LLMAgent(new ScriptedObservedClient(), new ToolRegistry(new ArrayClass([$this->tool()])), 25, false, null, null, null, $observer);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]), "system");

        $this->assertSame(LLMRunStopReason::done, $run->stopReason);
        $this->assertSame([
            "runWillStart",
            "iterationWillStart:1",
            "iterationDidEnd:1",
            "toolCallDidEnd:1:probe_tool",
            "iterationWillStart:2",
            "iterationDidEnd:2",
            "runDidEnd:done",
        ], $observer->events);
    }

    /**
     * The assembled context reaches the observer before the provider sees it, which is what lets a
     * caller report that history was dropped rather than discovering it from a shorter answer.
     */
    #[Test]
    public function iterationReportsTheAssembledContextItIsAboutToSend(): void
    {
        $observer = new RecordingRunObserver();
        $agent = new LLMAgent(new ScriptedObservedClient(), new ToolRegistry(new ArrayClass([$this->tool()])), 25, false, null, null, null, $observer);

        $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]), "system");

        $this->assertSame("system", $observer->assembledContexts[0]->systemPrompt);
        $this->assertSame(1, $observer->assembledContexts[0]->messages->count);
        $this->assertSame(3, $observer->assembledContexts[1]->messages->count);
    }

    /**
     * A repeated call never reaches the tool, so without this event nothing explains why the model
     * asked for it and no execution followed.
     */
    #[Test]
    public function cachedToolCallIsReportedAsCachedWithNoDuration(): void
    {
        $observer = new RecordingRunObserver();
        $agent = new LLMAgent(new ScriptedRepeatingClient(), new ToolRegistry(new ArrayClass([$this->tool()])), 25, false, null, null, null, $observer);

        $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]), null);

        $this->assertSame([false, true], $observer->toolCallWasCached);
        $this->assertSame(0.0, $observer->toolCallDurations[1]);
        $this->assertGreaterThanOrEqual(0.0, $observer->toolCallDurations[0]);
    }

    #[Test]
    public function subagentReportsUnderItsOwnIdentifierNamingItsParent(): void
    {
        $observer = new RecordingRunObserver();
        $agent = new LLMAgent(new ScriptedObservedSubagentClient(), new ToolRegistry(new ArrayClass([$this->tool()])), 25, true, null, null, null, $observer);

        $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "parent task")]), "system");

        $contexts = $observer->startedContexts;
        $this->assertCount(2, $contexts);
        $this->assertNull($contexts[0]->parentIdentifier);
        $this->assertFalse($contexts[0]->isSubagent);
        $this->assertSame(0, $contexts[0]->depth);
        $this->assertSame($contexts[0]->identifier, $contexts[1]->parentIdentifier);
        $this->assertTrue($contexts[1]->isSubagent);
        $this->assertSame(1, $contexts[1]->depth);
        $this->assertNotSame($contexts[0]->identifier, $contexts[1]->identifier);
    }

    /**
     * The subagent's own ending is reported to the observer even though the parent only ever sees
     * its answer as a tool result.
     */
    #[Test]
    public function bothRunsReportTheirOwnEnding(): void
    {
        $observer = new RecordingRunObserver();
        $agent = new LLMAgent(new ScriptedObservedSubagentClient(), new ToolRegistry(new ArrayClass([$this->tool()])), 25, true, null, null, null, $observer);

        $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "parent task")]), "system");

        $this->assertCount(2, $observer->endedRuns);
        $this->assertSame("sub answer", $observer->endedRuns[0]->messages->last->content);
        $this->assertSame("parent done", $observer->endedRuns[1]->messages->last->content);
    }

    /** A run stopped by a budget still ends, so the observer must be told why rather than left waiting. */
    #[Test]
    public function runStoppedByABudgetReportsItsEnding(): void
    {
        $observer = new RecordingRunObserver();
        $policy = new LLMExecutionPolicy(maxToolCalls: 0);
        $agent = new LLMAgent(new ScriptedObservedClient(), new ToolRegistry(new ArrayClass([$this->tool()])), 25, false, null, $policy, null, $observer);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]), null);

        $this->assertSame(LLMRunStopReason::toolCallLimit, $run->stopReason);
        $this->assertSame("runDidEnd:toolCallLimit", $observer->events[array_key_last($observer->events)]);
    }

    /** The observer is optional: every existing caller constructs an agent without one. */
    #[Test]
    public function runWithoutAnObserverBehavesIdentically(): void
    {
        $agent = new LLMAgent(new ScriptedObservedClient(), new ToolRegistry(new ArrayClass([$this->tool()])), 25, false);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]), "system");

        $this->assertSame(LLMRunStopReason::done, $run->stopReason);
        $this->assertTrue($run->isComplete);
    }

    private function tool(): AbstractTool
    {
        /** @var AbstractTool */
        return new ReflectionClass(ObservedProbeTool::class)->newInstanceWithoutConstructor();
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

final class RecordingRunObserver implements LLMRunObserver
{
    /** @var list<string> */
    public array $events = [];
    /** @var list<LLMRunContext> */
    public array $startedContexts = [];
    /** @var list<LLMContext> */
    public array $assembledContexts = [];
    /** @var list<bool> */
    public array $toolCallWasCached = [];
    /** @var list<float> */
    public array $toolCallDurations = [];
    /** @var list<LLMRun> */
    public array $endedRuns = [];

    #[Override]
    public function runWillStart(LLMRunContext $context): void
    {
        $this->events[] = "runWillStart";
        $this->startedContexts[] = $context;
    }

    #[Override]
    public function iterationWillStart(LLMRunContext $context, int $iteration, LLMContext $assembled): void
    {
        $this->events[] = "iterationWillStart:$iteration";
        $this->assembledContexts[] = $assembled;
    }

    #[Override]
    public function iterationDidEnd(LLMRunContext $context, int $iteration, LLMTurn $turn): void
    {
        $this->events[] = "iterationDidEnd:$iteration";
    }

    #[Override]
    public function toolCallDidEnd(LLMRunContext $context, int $iteration, LLMToolCall $call, string $result, bool $isError, bool $wasCached, float $duration): void
    {
        $this->events[] = "toolCallDidEnd:$iteration:$call->name";
        $this->toolCallWasCached[] = $wasCached;
        $this->toolCallDurations[] = $duration;
    }

    #[Override]
    public function runDidEnd(LLMRunContext $context, LLMRun $run): void
    {
        $this->events[] = "runDidEnd:{$run->stopReason->value}";
        $this->endedRuns[] = $run;
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
