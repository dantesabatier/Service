<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Tools\ToolRegistry;

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
    public function __construct(private LLMClient $client, private ToolRegistry $toolRegistry)
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
        while (true) {
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
                $result = $this->toolRegistry->call($toolCall->name, $toolCall->arguments);
                $text = $result->map(fn(ContentItem $item): string => $item->text)->join("\n");
                $toolMsg = new LLMMessage(LLMMessageRole::tool, $text, toolCallId: $toolCall->id);
                $history->append($toolMsg);
                $newMessages->append($toolMsg);
            }
        }
        return new LLMRun($newMessages, $totalInputTokens, $totalOutputTokens);
    }
}
