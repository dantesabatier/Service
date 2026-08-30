<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use InvalidArgumentException;

/** A synthetic tool result produced by an agent loop under the runtime's execution boundary. */
final readonly class LLMAgentToolResult
{
    /**
     * @param string $text The content to append as the tool result.
     * @param bool $isError Whether the content describes a failed invocation.
     * @param LLMRunStopReason|null $stopReason A run-level condition the result must propagate, or `null` to continue.
     * @param bool|null $isRetryable Whether another run may succeed when `$stopReason` is present.
     */
    public function __construct(public string $text, public bool $isError = false, public ?LLMRunStopReason $stopReason = null, public ?bool $isRetryable = null)
    {
        if ($stopReason === LLMRunStopReason::done) {
            throw new InvalidArgumentException("A tool result cannot complete an agent run before the model answers.");
        }
        if ($stopReason === null && $isRetryable !== null) {
            throw new InvalidArgumentException("A tool result without a stop reason has nothing to retry.");
        }
    }
}
