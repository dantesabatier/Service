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

    private function tool(): AbstractTool
    {
        /** @var AbstractTool */
        return (new ReflectionClass(LLMAgentProbeTool::class))->newInstanceWithoutConstructor();
    }

    private function countingTool(): AbstractTool
    {
        /** @var AbstractTool */
        return (new ReflectionClass(LLMAgentCountingTool::class))->newInstanceWithoutConstructor();
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

final class ScriptedNoAnswerSubagentClient extends LLMClient
{
    public string $version {
        get => "test";
    }
    public int $maxTokens {
        get => 1024;
    }

    private int $call = 0;

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): LLMTurn
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

/** Never concludes: every turn asks for a tool again, so only the iteration cap can stop the loop. */
final class ScriptedNeverDoneClient extends LLMClient
{
    public string $version {
        get => "test";
    }
    public int $maxTokens {
        get => 1024;
    }

    public int $calls = 0;

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): LLMTurn
    {
        $this->calls++;
        return new LLMTurn(null, new ArrayClass([new LLMToolCall("call-{$this->calls}", "probe_tool", new Dictionary(["n" => $this->calls]))]));
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

/**
 * The subagent emits text on its first turn and then never concludes, exhausting its cap. The
 * parent must report the cap rather than pass that intermediate text off as the answer.
 */
final class ScriptedExhaustedSubagentClient extends LLMClient
{
    public string $version {
        get => "test";
    }
    public int $maxTokens {
        get => 1024;
    }

    private int $call = 0;

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): LLMTurn
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

/** Calls the subagent tool with no `task`, the mis-call the parent must flag back to the model. */
final class ScriptedTasklessSubagentClient extends LLMClient
{
    public string $version {
        get => "test";
    }
    public int $maxTokens {
        get => 1024;
    }

    private int $call = 0;

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): LLMTurn
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

/** Issues the same call twice with the argument order swapped; only the first should reach the tool. */
final class ScriptedReorderedArgumentsClient extends LLMClient
{
    public string $version {
        get => "test";
    }
    public int $maxTokens {
        get => 1024;
    }

    private int $call = 0;

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): LLMTurn
    {
        return match ($this->call++) {
            0 => new LLMTurn(null, new ArrayClass([new LLMToolCall("c1", "counting_tool", new Dictionary(["a" => 1, "b" => "two"]))])),
            1 => new LLMTurn(null, new ArrayClass([new LLMToolCall("c2", "counting_tool", new Dictionary(["b" => "two", "a" => 1]))])),
            2 => new LLMTurn("done", new ArrayClass()),
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

/** Same keys, different values: two genuinely distinct calls that must both reach the tool. */
final class ScriptedDistinctArgumentsClient extends LLMClient
{
    public string $version {
        get => "test";
    }
    public int $maxTokens {
        get => 1024;
    }

    private int $call = 0;

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    public function complete(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): LLMTurn
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

/**
 * Counts how many times it actually executed, so a cache hit is observable.
 *
 * The counter is static because `AbstractTool`'s constructor needs a Core Data context this
 * test has no use for, so instances are built with `newInstanceWithoutConstructor()` and
 * cannot initialise instance state. Reset it in the test's arrange step.
 */
final class LLMAgentCountingTool extends AbstractTool
{
    public static int $calls = 0;

    public string $name {
        get => "counting_tool";
    }
    public string $description {
        get => "Counting tool.";
    }
    public array $inputSchema {
        get => ["type" => "object"];
    }

    /** @return ArrayClass<ContentItem> */
    public function execute(Dictionary $arguments): ArrayClass
    {
        self::$calls++;
        return new ArrayClass([new ContentItem("text", "counted " . self::$calls)]);
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
