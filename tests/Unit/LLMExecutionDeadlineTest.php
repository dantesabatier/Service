<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Service\LLM\LLMClock;
use Sabatier\Service\LLM\LLMDeadlineExceededException;
use Sabatier\Service\LLM\LLMExecutionDeadline;

final class LLMExecutionDeadlineTest extends TestCase
{
    #[Test]
    public function finiteDeadlineTracksTheRemainingMonotonicTime(): void
    {
        $clock = new MutableDeadlineClock(10.0);
        $deadline = new LLMExecutionDeadline($clock, 5.0);

        $this->assertSame(5.0, $deadline->remainingTime);
        $this->assertFalse($deadline->hasExpired);

        $clock->time = 13.5;

        $this->assertSame(1.5, $deadline->remainingTime);
        $deadline->enforce();
    }

    #[Test]
    public function deadlineThrowsAtExpirationWithoutReportingNegativeTime(): void
    {
        $clock = new MutableDeadlineClock(10.0);
        $deadline = new LLMExecutionDeadline($clock, 2.0);
        $clock->time = 12.5;

        $this->assertSame(0.0, $deadline->remainingTime);
        $this->assertTrue($deadline->hasExpired);

        $this->expectException(LLMDeadlineExceededException::class);
        $deadline->enforce();
    }

    #[Test]
    public function unboundedDeadlineNeverExpires(): void
    {
        $clock = new MutableDeadlineClock(10.0);
        $deadline = new LLMExecutionDeadline($clock, null);
        $clock->time = 1_000_000.0;

        $this->assertNull($deadline->remainingTime);
        $this->assertFalse($deadline->hasExpired);
        $deadline->enforce();
    }
}

final class MutableDeadlineClock implements LLMClock
{
    #[Override]
    public float $timestamp {
        get => $this->time;
    }
    #[Override]
    public float $monotonicTime {
        get => $this->time;
    }

    /** @param float $time Initial wall and monotonic time. */
    public function __construct(public float $time)
    {
    }
}
