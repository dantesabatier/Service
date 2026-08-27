<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** A scalar snapshot of one agent-loop boundary, safe for an observer to retain or transmit. */
interface LLMRunEvent
{
    /** @var LLMRunContext Identity and ancestry of the run that emitted the event. */
    public LLMRunContext $context {
        get;
    }
    /** @var float Unix timestamp at which the event occurred. */
    public float $timestamp {
        get;
    }
}
