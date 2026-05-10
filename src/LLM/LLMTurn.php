<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;

/** One complete response from the model: optional text, zero or more tool calls, and the tokens consumed. `$isDone` is true when the model returned no tool calls, signalling that the agentic loop should stop. */
final readonly class LLMTurn
{
    public bool $isDone;

    /**
     * @param string|null $text
     * @param ArrayClass<LLMToolCall> $toolCalls
     * @param int<0, max> $inputTokens
     * @param int<0, max> $outputTokens
     * @param ArrayClass<array{type: string, thinking: string, signature: string}>|null $thinkingBlocks Raw Anthropic thinking blocks that must be echoed back on the next turn.
     * @param string|null $reasoningContent OpenAI-compatible reasoning string that must be echoed back on the next turn.
     */
    public function __construct(public ?string $text, public ArrayClass $toolCalls, public int $inputTokens = 0, public int $outputTokens = 0, public ?ArrayClass $thinkingBlocks = null, public ?string $reasoningContent = null)
    {
        $this->isDone = $this->toolCalls->isEmpty;
    }
}
