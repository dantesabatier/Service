<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Exception;

/** @internal */
final class LLMRunInterruptedException extends Exception
{
    /**
     * @param LLMRunStopReason $stopReason
     * @param bool|null $isRetryable
     */
    public function __construct(public readonly LLMRunStopReason $stopReason, public readonly ?bool $isRetryable)
    {
        parent::__construct($stopReason->value);
    }
}
