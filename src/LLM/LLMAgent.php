<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Throwable;

/**
 * Drives the agentic loop for a single run.
 *
 * Repeatedly calls the LLM client, executes any tool calls via the ToolRegistry, and feeds
 * results back into the conversation until the model returns a turn with no tool calls
 * (`LLMTurn::$isDone`). Returns an `LLMRun` containing only the messages generated during
 * this run (not the input history) and the total tokens consumed.
 */
final readonly class LLMAgent
{
    public function __construct(private LLMClient $client, private ToolRegistry $toolRegistry, private int $maxIterations = 25)
    {
    }

    /**
     * Runs the agentic loop and returns only the new messages generated (not the input).
     *
     * @param ArrayClass<LLMMessage> $messages
     * @param string|null $systemPrompt
     * @return LLMRun
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
            $turn = $this->client->complete($history, $this->toolRegistry->list, $systemPrompt);
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
                    try {
                        $result = $this->toolRegistry->call($toolCall->name, $toolCall->arguments);
                        $text = $result->map(fn(ContentItem $item): string => $item->text)->join("\n");
                        $toolCallCache[$cacheKey] = $text;
                    } catch (Throwable $throwable) {
                        // A tool that throws must not abort the run — feed the error back to the
                        // model as the tool result, flagged as an error so the model knows the call
                        // failed. Not cached: the same call may succeed once the model fixes it.
                        $text = "Error: {$throwable->getMessage()}";
                        $isError = true;
                    }
                }
                $toolMsg = new LLMMessage(LLMMessageRole::tool, $text, toolCallId: $toolCall->id, isError: $isError);
                $history->append($toolMsg);
                $newMessages->append($toolMsg);
            }
        }
        return new LLMRun($newMessages, $totalInputTokens, $totalOutputTokens);
    }
}
