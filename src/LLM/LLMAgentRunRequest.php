<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;

/** One agent invocation, isolated from the conversation collection supplied by its caller. */
final readonly class LLMAgentRunRequest
{
    /**
     * @param ArrayClass<LLMMessage> $messages A disposable snapshot of the input conversation.
     * @param string|null $systemPrompt The system prompt inherited by the run, or `null` for none.
     */
    public function __construct(public ArrayClass $messages, public ?string $systemPrompt = null)
    {
    }
}
