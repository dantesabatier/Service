<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** Supplies wall and monotonic time to agent execution and trace events. */
interface LLMClock
{
    /** @var float Current Unix timestamp for externally meaningful event ordering. */
    public float $timestamp {
        get;
    }
    /** @var float Current monotonic time in seconds for durations and deadlines. */
    public float $monotonicTime {
        get;
    }
}
