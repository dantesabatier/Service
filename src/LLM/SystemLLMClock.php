<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Override;
use Sabatier\Foundation\ProcessInfo;

/** The production agent clock, combining Unix time with the process monotonic clock. */
final class SystemLLMClock implements LLMClock
{
    #[Override]
    public float $timestamp {
        get => microtime(true);
    }
    #[Override]
    public float $monotonicTime {
        get => ProcessInfo::processInfo()->systemUptime;
    }
}
