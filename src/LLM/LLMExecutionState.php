<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** @internal */
final class LLMExecutionState
{
    private(set) int $toolCalls = 0;
    private(set) int $subagentCalls = 0;
    private(set) int $inputTokens = 0;
    private(set) int $outputTokens = 0;

    public function consumeToolCall(LLMExecutionPolicy $policy, bool $isSubagent): ?LLMRunStopReason
    {
        if ($policy->maxToolCalls !== null && $this->toolCalls >= $policy->maxToolCalls) {
            return LLMRunStopReason::toolCallLimit;
        }
        if ($isSubagent && $policy->maxSubagentCalls !== null && $this->subagentCalls >= $policy->maxSubagentCalls) {
            return LLMRunStopReason::subagentCallLimit;
        }
        $this->toolCalls++;
        if ($isSubagent) {
            $this->subagentCalls++;
        }
        return null;
    }

    /**
     * @param int<0, max> $inputTokens
     * @param int<0, max> $outputTokens
     */
    public function recordTokens(int $inputTokens, int $outputTokens): void
    {
        $this->inputTokens += $inputTokens;
        $this->outputTokens += $outputTokens;
    }

    /**
     * Returns the first token limit that prevents another model turn.
     *
     * `requiresCapacity` is true before another provider call: reaching a limit exactly leaves no
     * capacity for it. After a completed provider call only an actual overrun counts, so an answer
     * that lands exactly on its budget remains a valid answer.
     */
    public function tokenStopReason(LLMExecutionPolicy $policy, bool $requiresCapacity): ?LLMRunStopReason
    {
        $comparison = $requiresCapacity ? fn(int $used, int $limit): bool => $used >= $limit : fn(int $used, int $limit): bool => $used > $limit;
        if ($policy->maxInputTokens !== null && $comparison($this->inputTokens, $policy->maxInputTokens)) {
            return LLMRunStopReason::inputTokenLimit;
        }
        if ($policy->maxOutputTokens !== null && $comparison($this->outputTokens, $policy->maxOutputTokens)) {
            return LLMRunStopReason::outputTokenLimit;
        }
        if ($policy->maxTotalTokens !== null && $comparison($this->inputTokens + $this->outputTokens, $policy->maxTotalTokens)) {
            return LLMRunStopReason::totalTokenLimit;
        }
        return null;
    }
}
