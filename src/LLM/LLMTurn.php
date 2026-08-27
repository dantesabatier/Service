<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;

/** One complete response from the model: optional text, zero or more tool calls, the tokens consumed, and the normalized reason the provider stopped. */
final readonly class LLMTurn
{
    public bool $isDone;
    public LLMTurnStopReason $stopReason;

    /**
     * @param string|null $text
     * @param ArrayClass<LLMToolCall> $toolCalls
     * @param int<0, max> $inputTokens
     * @param int<0, max> $outputTokens
     * @param ArrayClass<Dictionary<string>>|null $thinkingBlocks Raw Anthropic thinking blocks that must be echoed back on the next turn.
     * @param string|null $reasoningContent OpenAI-compatible reasoning string that must be echoed back on the next turn.
     * @param LLMTurnStopReason|null $stopReason Why the provider ended the turn. Tool calls always imply `toolUse`; a turn without calls defaults to `completed` for custom clients that do not expose a provider reason.
     */
    public function __construct(public ?string $text, public ArrayClass $toolCalls, public int $inputTokens = 0, public int $outputTokens = 0, public ?ArrayClass $thinkingBlocks = null, public ?string $reasoningContent = null, ?LLMTurnStopReason $stopReason = null)
    {
        $this->stopReason = $this->toolCalls->isEmpty ? ($stopReason ?? LLMTurnStopReason::completed) : LLMTurnStopReason::toolUse;
        $this->isDone = $this->stopReason === LLMTurnStopReason::completed;
    }
}
