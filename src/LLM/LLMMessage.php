<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;

/** A single message in an LLM conversation, carrying its role, text content, any tool calls or tool result id, optional image attachments, and the token count charged for generating it. */
final readonly class LLMMessage
{
    /**
     * @param LLMMessageRole $role
     * @param string|null $content
     * @param ArrayClass<LLMToolCall>|null $toolCalls
     * @param string|null $toolCallId
     * @param ArrayClass<Dictionary<string>>|null $images
     * @param int $outputTokens
     * @param ArrayClass<Dictionary<string>>|null $thinkingBlocks Raw Anthropic thinking blocks; must be echoed back verbatim on the next turn.
     * @param string|null $reasoningContent OpenAI-compatible reasoning string; must be echoed back on the next turn.
     * @param bool $isError Marks a tool-result message as a failure so the provider flags it to the model (e.g. Anthropic `is_error`).
     */
    public function __construct(public LLMMessageRole $role, public ?string $content, public ?ArrayClass $toolCalls = null, public ?string $toolCallId = null, public ?ArrayClass $images = null, public int $outputTokens = 0, public ?ArrayClass $thinkingBlocks = null, public ?string $reasoningContent = null, public bool $isError = false)
    {
    }
}
