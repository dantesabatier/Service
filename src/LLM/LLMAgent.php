<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Throwable;

/**
 * Drives the agentic loop for a single run.
 *
 * Repeatedly calls the LLM client, executes any tool calls via the ToolRegistry, and feeds
 * results back into the conversation until the model returns a turn with no tool calls
 * (`LLMTurn::$isDone`). Returns an `LLMRun` containing only the messages generated during
 * this run (not the input history) and the total tokens consumed.
 *
 * Tool failures are resolved by the registry, not here. A correctable mistake — the model
 * mis-called a tool — comes back as a failed `ToolResult` and is fed to the model as an
 * error-flagged tool message so the loop can self-correct; it is not cached, since the same
 * call may succeed once corrected. A real program fault propagates out of the registry and
 * aborts the run for the caller to handle.
 */
final class LLMAgent
{
    private const string subagentToolName = "run_subagent";
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
     * @return LLMRun The messages generated during this run and the total input and output tokens consumed.
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
        /** @var array<string, string> $toolCallCache */
        $toolCallCache = [];
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
                $cacheKey = md5($toolCall->name . $toolCall->arguments->description);
                $isError = false;
                if (isset($toolCallCache[$cacheKey])) {
                    $text = "You already called this tool with these exact arguments. Result: " . $toolCallCache[$cacheKey] . " Do not call it again.";
                } else {
                    if ($this->canSpawnSubagents && $toolCall->name === self::subagentToolName) {
                        $subRun = $this->runSubagent($toolCall->arguments, $systemPrompt);
                        $totalInputTokens += $subRun->inputTokens;
                        $totalOutputTokens += $subRun->outputTokens;
                        $text = $this->subagentResultText($subRun);
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
     * @param Dictionary<mixed> $arguments
     * @throws Throwable
     */
    private function runSubagent(Dictionary $arguments, ?string $parentSystemPrompt): LLMRun
    {
        $task = trim((string)$arguments["task"]);
        if ($task === "") {
            return new LLMRun(new ArrayClass([new LLMMessage(LLMMessageRole::assistant, "Subagent task is required.")]));
        }
        $context = trim((string)$arguments["context"]);
        $content = $context === "" ? $task : "Task:\n$task\n\nContext:\n$context";
        $systemPrompt = trim((string)$arguments["systemPrompt"]);
        $subagent = new self($this->client, $this->toolRegistry, self::subagentMaxIterations, false);
        return $subagent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, $content)]), $systemPrompt === "" ? $parentSystemPrompt : $systemPrompt);
    }

    /**
     * Returns the subagent's final answer, or an explicit sentinel when the sub-run
     * produced no assistant content to report back.
     */
    private function subagentResultText(LLMRun $run): string
    {
        return $run->messages->last(fn(LLMMessage $message): bool => $message->role === LLMMessageRole::assistant && !empty($message->content))?->content ?? "The subagent produced no answer.";
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
