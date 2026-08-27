<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;

/** The provider-neutral context assembled for one model turn, including truncation metadata. */
final readonly class LLMContext
{
    /**
     * @param ArrayClass<LLMMessage> $messages The history to send to the provider.
     * @param string|null $systemPrompt The system prompt to send separately, or `null` for none.
     * @param int<0, max> $omittedMessageCount Messages removed from the original history.
     * @param bool $wasCompacted Whether a summary of omitted history was inserted.
     * @param bool $isWithinLimit Whether the assembled context satisfies every configured limit.
     */
    public function __construct(public ArrayClass $messages, public ?string $systemPrompt, public int $omittedMessageCount = 0, public bool $wasCompacted = false, public bool $isWithinLimit = true)
    {
    }
}
