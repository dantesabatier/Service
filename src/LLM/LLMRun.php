<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;

/**
 * The aggregate result of a complete agentic run: all messages generated after the initial input, and the total tokens consumed across every turn.
 *
 * `$isComplete` answers the one question most callers have — is this an answer or not — and `$stopReason` says which ending produced it, because the three call for different corrections: retry, narrow the task, or fix the configuration.
 *
 * `$isComplete` stays derived rather than passed, so it cannot contradict the messages it summarises: a run is complete when its last message is an assistant message carrying no tool calls — the same condition `LLMTurn::$isDone` expresses for a single turn — and only when the run also reached that point on its own. A capped run always ends on a tool message, because the loop executes every tool call of a turn before re-testing the cap. A failed run is never complete however it ends: the provider stopped answering partway, and the messages already collected are not a conclusion. An empty run is not complete either: nothing answered it.
 */
final class LLMRun
{
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
            /** @var ArrayClass<LLMToolCallResult> $results */
            $results = new ArrayClass();
            foreach ($this->messages as $message) {
                foreach ($message->toolCalls ?? new ArrayClass() as $call) {
                    $results->append(new LLMToolCallResult($call, $contents[$call->id], $failures[$call->id] === true));
                }
            }
            return $results;
        }
    }

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param int<0, max> $inputTokens
     * @param int<0, max> $outputTokens
     * @param LLMRunStopReason $stopReason Why the loop stopped. Defaults to `done`, leaving `$isComplete` to the messages alone.
     */
    public function __construct(public readonly ArrayClass $messages, public readonly int $inputTokens = 0, public readonly int $outputTokens = 0, public readonly LLMRunStopReason $stopReason = LLMRunStopReason::done)
    {
        $last = $this->messages->last;
        $this->isComplete = $this->stopReason === LLMRunStopReason::done && $last !== null && $last->role === LLMMessageRole::assistant && $last->toolCalls?->isEmpty !== false;
    }
}