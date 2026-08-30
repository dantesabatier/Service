<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use InvalidArgumentException;

/** The terminal decision made by an orchestration strategy after the runtime has recorded the run itself. */
final readonly class LLMAgentLoopOutcome
{
    /**
     * @param LLMRunStopReason $stopReason Why the strategy stopped.
     * @param bool|null $isRetryable Whether repeating an incomplete run may succeed.
     */
    public function __construct(public LLMRunStopReason $stopReason = LLMRunStopReason::done, public ?bool $isRetryable = null)
    {
        if ($stopReason === LLMRunStopReason::done && $isRetryable !== null) {
            throw new InvalidArgumentException("A completed agent run has nothing to retry.");
        }
    }
}
