<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;

/**
 * The aggregate result of a complete agentic run: all messages generated after the initial input, and the total tokens consumed across every turn.
 *
 * `$isComplete` answers the one question most callers have — is this an answer or not — and `$stopReason` says which ending produced it, because a model-provider failure, tool-provider failure, output limit, refusal, deadline, iteration cap, execution budget, context limit, and write-approval boundary call for different corrections.
 *
 * `$isRetryable` answers a separate question the stop reason cannot: whether running the same thing again is worth the tokens. The two are deliberately apart. They vary independently — a provider failure is worth retrying when it was a 429 and pointless when it was a bad API key, and the same split will apply to a deadline the caller can afford to raise. Folding the answer into `$stopReason` would double its cases for every ending that gains the distinction.
 *
 * The class marks its properties `readonly` one by one rather than declaring itself `final readonly`, which would be shorter: a `readonly` class forbids property hooks, and `$toolCallResults` is one. Every stored property still carries the keyword, so the run is as immutable as the shorter form would have made it — only the computed property is exempt, and it has nothing to store.
 *
 * `$isComplete` stays derived rather than passed, so it cannot contradict the messages it summarizes: a run is complete when its last message is an assistant message carrying no tool calls — the same condition `LLMTurn::$isDone` expresses for a single turn — and only when the runtime retained `done` as its terminal outcome. A failed or capped run is never complete; however, its strategy left the conversation: the work did not reach a conclusion. An empty run is not complete either because nothing answered it.
 */
final class LLMRun
{
    /** @var bool Whether the model concluded the run with a complete assistant response. */
    public readonly bool $isComplete;
    /** @var ArrayClass<LLMToolCallResult> Every tool call the model made, rejoined with the result that came back for it, in the order the calls were made. The run stores the two apart, as the wire format does: a call rides on an assistant message and its result arrives later as a `tool` message keyed by `toolCallId`. A call the run stopped before answering keeps a `null` content rather than being left out, so the caller sees that it was made. */
    public ArrayClass $toolCallResults {
        get {
            /** @var Dictionary<string> $contents */
            $contents = new Dictionary();
            /** @var Dictionary<bool> $failures */
            $failures = new Dictionary();
            foreach ($this->messages as $message) {
                if ($message->role !== LLMMessageRole::tool || $message->toolCallId === null) {
                    continue;
                }
                $contents[$message->toolCallId] = $message->content;
                $failures[$message->toolCallId] = $message->isError;
            }
            return $this->messages->flatMap(fn(LLMMessage $message): ArrayClass => $message->toolCalls ?? new ArrayClass())->map(fn(LLMToolCall $call): LLMToolCallResult => new LLMToolCallResult($call, $contents[$call->id], $failures[$call->id] === true));
        }
    }

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param int<0, max> $inputTokens
     * @param int<0, max> $outputTokens
     * @param LLMRunStopReason $stopReason Why the loop stopped. Defaults to `done`, leaving `$isComplete` to the messages alone.
     * @param bool|null $isRetryable Whether running the same thing again is worth attempting, or `null` when the question does not apply — a run the model concluded has nothing to retry. A caller can tell that from a definite `false`, which says the ending will repeat itself.
     */
    public function __construct(public readonly ArrayClass $messages, public readonly int $inputTokens = 0, public readonly int $outputTokens = 0, public readonly LLMRunStopReason $stopReason = LLMRunStopReason::done, public readonly ?bool $isRetryable = null)
    {
        $last = $this->messages->last;
        $this->isComplete = $this->stopReason === LLMRunStopReason::done && $last !== null && $last->role === LLMMessageRole::assistant && $last->toolCalls?->isEmpty !== false;
    }
}
