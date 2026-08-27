<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Closure;
use InvalidArgumentException;

/**
 * Hard limits and write-approval policy applied to one agentic run and every subagent it launches.
 *
 * Token limits count the cumulative usage reported by providers, not the size of the latest
 * request. A write is denied by default; an application that has obtained user approval supplies
 * a closure that accepts only the concrete calls covered by that approval.
 */
final readonly class LLMExecutionPolicy
{
    /**
     * @param int|null $maxToolCalls Maximum calls handled across the parent and every subagent, including repeated calls served from cache, or `null` for no limit.
     * @param int|null $maxSubagentCalls Maximum subagents launched across the whole run, or `null` for no limit.
     * @param int|null $maxInputTokens Maximum cumulative provider input tokens, or `null` for no limit.
     * @param int|null $maxOutputTokens Maximum cumulative provider output tokens, or `null` for no limit.
     * @param int|null $maxTotalTokens Maximum cumulative input plus output tokens, or `null` for no limit.
     * @param Closure(LLMToolCall): bool|null $writeApproval Returns true only for a state-changing call the user approved. Null denies every write.
     */
    public function __construct(public ?int $maxToolCalls = 64, public ?int $maxSubagentCalls = 8, public ?int $maxInputTokens = null, public ?int $maxOutputTokens = null, public ?int $maxTotalTokens = null, private ?Closure $writeApproval = null)
    {
        foreach (["maxToolCalls" => $maxToolCalls, "maxSubagentCalls" => $maxSubagentCalls, "maxInputTokens" => $maxInputTokens, "maxOutputTokens" => $maxOutputTokens, "maxTotalTokens" => $maxTotalTokens] as $name => $limit) {
            if ($limit !== null && $limit < 0) {
                throw new InvalidArgumentException("$name cannot be negative.");
            }
        }
    }

    /**
     * Whether a state-changing call is covered by the application's approval.
     *
     * @param LLMToolCall $call The concrete call requesting approval.
     */
    public function approvesWrite(LLMToolCall $call): bool
    {
        return $this->writeApproval !== null && ($this->writeApproval)($call) === true;
    }
}
