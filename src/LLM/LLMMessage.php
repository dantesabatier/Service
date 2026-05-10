<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;

/** A single message in an LLM conversation, carrying its role, text content, any tool calls or tool result id, optional image attachments, and the token count charged for generating it. */
final readonly class LLMMessage
{
    /**
     * @param LLMMessageRole $role
     * @param string|null $content
     * @param ArrayClass<LLMToolCall>|null $toolCalls
     * @param string|null $toolCallId
     * @param ArrayClass<array{name: string, mimeType: string, data: string}>|null $images
     * @param int $outputTokens
     * @param ArrayClass<array{type: string, thinking: string, signature: string}>|null $thinkingBlocks Raw Anthropic thinking blocks; must be echoed back verbatim on the next turn.
     * @param string|null $reasoningContent OpenAI-compatible reasoning string; must be echoed back on the next turn.
     */
    public function __construct(public LLMMessageRole $role, public ?string $content, public ?ArrayClass $toolCalls = null, public ?string $toolCallId = null, public ?ArrayClass $images = null, public int $outputTokens = 0, public ?ArrayClass $thinkingBlocks = null, public ?string $reasoningContent = null)
    {
    }
}
