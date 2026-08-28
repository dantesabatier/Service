<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** A live monotonic deadline shared by a root agent run and all of its subagents. */
final class LLMExecutionDeadline
{
    private readonly ?float $expiration;

    /** @var float|null Seconds remaining now, or `null` when execution is unbounded. */
    public ?float $remainingTime {
        get => $this->expiration === null ? null : max(0.0, $this->expiration - $this->clock->monotonicTime);
    }
    /** @var bool Whether no execution time remains. */
    public bool $hasExpired {
        get => $this->remainingTime === 0.0;
    }

    /**
     * @param LLMClock $clock The monotonic clock used by the complete run tree.
     * @param float|null $timeLimit Seconds available from construction, or `null` for no deadline.
     */
    public function __construct(private readonly LLMClock $clock, ?float $timeLimit)
    {
        $this->expiration = $timeLimit === null ? null : $clock->monotonicTime + $timeLimit;
    }

    /**
     * Enforces the deadline at the current monotonic time.
     *
     * @throws LLMDeadlineExceededException When the shared execution window has expired.
     */
    public function enforce(): void
    {
        if ($this->hasExpired) {
            throw new LLMDeadlineExceededException();
        }
    }
}
