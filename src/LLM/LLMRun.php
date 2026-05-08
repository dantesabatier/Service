<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;

/** The aggregate result of a complete agentic run: all messages generated after the initial input, and the total tokens consumed across every turn. */
final readonly class LLMRun
{
    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param int<0, max> $inputTokens
     * @param int<0, max> $outputTokens
     */
    public function __construct(public ArrayClass $messages, public int $inputTokens = 0, public int $outputTokens = 0)
    {
    }
}
