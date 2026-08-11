<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Throwable;
use function Sabatier\Foundation\human_readable_value;

/**
 * Drives the agentic loop for a single run.
 *
 * Repeatedly calls the LLM client, executes any tool calls via the ToolRegistry, and feeds
 * results back into the conversation until the model returns a turn with no tool calls
 * (`LLMTurn::$isDone`). Returns an `LLMRun` containing only the messages generated during
 * this run (not the input history) and the total tokens consumed.
 *
 * The loop also stops once it has run `$maxIterations` turns without the model concluding.
 * That run is reported as incomplete (`LLMRun::$isComplete`), since its last message is a
 * tool result rather than an answer; a caller that treats the two alike presents unfinished
 * work as a conclusion.
 *
 * Tool failures are resolved by the registry, not here. A correctable mistake — the model
 * mis-called a tool — comes back as a failed `ToolResult` and is fed to the model as an
 * error-flagged tool message so the loop can self-correct; it is not cached, since the same
 * call may succeed once corrected. A real program fault propagates out of the registry and
 * aborts the run for the caller to handle.
 *
 * Successful calls are cached for the length of the run, keyed on the tool name and arguments
 * regardless of the order the model emitted them in, so a model that loops on one call is told
 * what it already got back instead of paying for the call again.
 */
final class LLMAgent
{
    private const string subagentToolName = "run_subagent";
    private const string subagentNoAnswer = "The subagent produced no answer.";
    private const string subagentTaskRequired = "Subagent task is required.";
    private const string subagentIncomplete = "The subagent ran out of iterations before finishing. Narrow the task and try again.";
    private const int subagentMaxIterations = 8;
    private static ?ToolDescriptor $subagentToolDescriptor = null;
    /** @var ArrayClass<ToolDescriptor> */
    private ArrayClass $toolList {
        get {
            if (isset($this->toolList)) {
                return $this->toolList;
            }
            if ($this->canSpawnSubagents) {
                return $this->toolList = $this->toolRegistry->list->appending(self::subagentToolDescriptor());
            }
            return $this->toolList = $this->toolRegistry->list;
        }
    }

    public function __construct(private readonly LLMClient $client, private readonly ToolRegistry $toolRegistry, private readonly int $maxIterations = 25, private readonly bool $canSpawnSubagents = true)
    {
    }

    /**
     * Runs the agentic loop and returns only the new messages generated (not the input).
     *
     * @param ArrayClass<LLMMessage> $messages The input conversation history; cloned, never mutated.
     * @param string|null $systemPrompt The system prompt to send on every turn, or null for none.
     * @return LLMRun The messages generated during this run, the total input and output tokens consumed, and whether the model concluded or the iteration cap stopped it.
     * @throws Throwable A fatal program fault raised by a tool, left to propagate out of the run.
     */
    public function run(ArrayClass $messages, ?string $systemPrompt = null): LLMRun
    {
        $history = clone $messages;
        /** @var ArrayClass<LLMMessage> $newMessages */
        $newMessages = new ArrayClass();
        /** @var int<0, max> $totalInputTokens */
        $totalInputTokens = 0;
        /** @var int<0, max> $totalOutputTokens */
        $totalOutputTokens = 0;
        /** @var Dictionary<string> $toolCallCache */
        $toolCallCache = new Dictionary();
        $iterations = 0;
        while ($iterations++ < $this->maxIterations) {
            $turn = $this->client->complete($history, $this->toolList, $systemPrompt);
            $totalInputTokens += $turn->inputTokens;
            $totalOutputTokens += $turn->outputTokens;
            $assistantMsg = new LLMMessage(LLMMessageRole::assistant, $turn->text, $turn->toolCalls, outputTokens: $turn->outputTokens, thinkingBlocks: $turn->thinkingBlocks, reasoningContent: $turn->reasoningContent);
            $history->append($assistantMsg);
            $newMessages->append($assistantMsg);
            if ($turn->isDone) {
                break;
            }
            foreach ($turn->toolCalls as $toolCall) {
                $cacheKey = self::toolCallCacheKey($toolCall);
                $isError = false;
                $cached = $toolCallCache[$cacheKey];
                if ($cached !== null) {
                    $text = self::repeatedCallText($cached);
                } else {
                    if ($this->canSpawnSubagents && $toolCall->name === self::subagentToolName) {
                        $subRun = $this->runSubagent($toolCall->arguments, $systemPrompt);
                        $totalInputTokens += $subRun->inputTokens;
                        $totalOutputTokens += $subRun->outputTokens;
                        $answer = $this->subagentResultText($subRun);
                        $text = $answer ?? $this->subagentFailureText($subRun);
                        $isError = $answer === null;
                    } else {
                        $result = $this->toolRegistry->call($toolCall->name, $toolCall->arguments);
                        $text = $result->text;
                        $isError = $result->isError;
                    }
                    if (!$isError) {
                        $toolCallCache[$cacheKey] = $text;
                    }
                }
                $toolMsg = new LLMMessage(LLMMessageRole::tool, $text, toolCallId: $toolCall->id, isError: $isError);
                $history->append($toolMsg);
                $newMessages->append($toolMsg);
            }
        }
        return new LLMRun($newMessages, $totalInputTokens, $totalOutputTokens);
    }

    /**
     * Identifies a tool call by name and arguments, independently of the order the model
     * happened to emit those arguments in.
     *
     * `Dictionary::$description` serializes in insertion order, so keying on it directly gives
     * `{a: 1, b: 2}` and `{b: 2, a: 1}` different keys for what is the same call. Sorting the
     * keys first makes the identity depend on the arguments themselves rather than on their
     * order. Only the top level is sorted; a nested dictionary that differs solely in ordering
     * still misses, which costs a cache hit and never a wrong one.
     */
    private static function toolCallCacheKey(LLMToolCall $toolCall): string
    {
        $arguments = $toolCall->arguments;
        $canonical = $arguments->keys->sort()->map(fn(string $key): string => sprintf("%s: %s", $key, human_readable_value($arguments[$key])))->join(", ");
        return md5("$toolCall->name($canonical)");
    }

    /**
     * Tells the model it has already made this exact call, and what it returned.
     *
     * The instruction comes first: a long cached result would otherwise push it past the point
     * where the model is still reading closely, which is precisely the case where the repeated
     * call is most expensive.
     */
    private static function repeatedCallText(string $cached): string
    {
        return "Do not call this tool with these arguments again — you already did, and it returned:\n\n$cached";
    }

    /**
     * @param Dictionary<mixed> $arguments
     * @throws Throwable
     */
    private function runSubagent(Dictionary $arguments, ?string $parentSystemPrompt): LLMRun
    {
        $task = trim((string)$arguments["task"]);
        if ($task === "") {
            return new LLMRun(new ArrayClass());
        }
        $context = trim((string)$arguments["context"]);
        $content = $context === "" ? $task : "Task:\n$task\n\nContext:\n$context";
        $systemPrompt = trim((string)$arguments["systemPrompt"]);
        $subagent = new self($this->client, $this->toolRegistry, self::subagentMaxIterations, false);
        return $subagent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, $content)]), $systemPrompt === "" ? $parentSystemPrompt : $systemPrompt);
    }

    /**
     * Returns the subagent's final answer, or `null` when the sub-run produced
     * no assistant content to report back.
     *
     * A sub-run stopped by the iteration cap never reaches a concluding turn, so the last
     * assistant text it produced is a step in the middle of the work, not an answer. Reporting
     * it as one would launder an unfinished run into a confident result for the parent, so the
     * cap is surfaced as a failure instead.
     */
    private function subagentResultText(LLMRun $run): ?string
    {
        if (!$run->isComplete) {
            return null;
        }
        return $run->messages->last(fn(LLMMessage $message): bool => $message->role === LLMMessageRole::assistant && $message->content !== null && $message->content !== "")?->content;
    }

    /**
     * Explains why a sub-run yielded no answer, in terms the model can act on.
     *
     * The three failures are distinct and call for different corrections: a run that never
     * started because the call omitted `task`, one that finished with nothing to say, and one
     * the iteration cap cut short. Collapsing them into one message would tell the model to
     * retry the case it should reformulate, and reformulate the case it should retry.
     */
    private function subagentFailureText(LLMRun $run): string
    {
        if ($run->messages->isEmpty) {
            return self::subagentTaskRequired;
        }
        return $run->isComplete ? self::subagentNoAnswer : self::subagentIncomplete;
    }

    private static function subagentToolDescriptor(): ToolDescriptor
    {
        return self::$subagentToolDescriptor ??= new ToolDescriptor(
            self::subagentToolName,
            "Launch a focused subagent with the same tool catalogue to complete one bounded task. The subagent cannot launch further subagents.",
            [
                "type" => "object",
                "properties" => [
                    "task" => ["type" => "string", "description" => "The focused task the subagent should complete."],
                    "context" => ["type" => "string", "description" => "Optional context the subagent needs to complete the task."],
                    "systemPrompt" => ["type" => "string", "description" => "Optional system prompt override for the subagent. Omit to inherit the parent system prompt."],
                ],
                "required" => ["task"],
                "additionalProperties" => false,
            ],
            "Run Subagent"
        );
    }
}
