<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;

/**
 * The aggregate result of a complete agentic run: all messages generated after the initial
 * input, and the total tokens consumed across every turn.
 *
 * `$isComplete` distinguishes a run the model ended on its own from one the iteration cap cut
 * short. It is derived, not passed: a run is complete when its last message is an assistant
 * message carrying no tool calls — the same condition `LLMTurn::$isDone` expresses for a single
 * turn. A capped run always ends on a tool message, because the loop executes every tool call
 * of a turn before re-testing the cap, so the two cases never overlap. An empty run is not
 * complete: nothing answered it.
 */
final readonly class LLMRun
{
    public bool $isComplete;

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param int<0, max> $inputTokens
     * @param int<0, max> $outputTokens
     */
    public function __construct(public ArrayClass $messages, public int $inputTokens = 0, public int $outputTokens = 0)
    {
        $last = $this->messages->last;
        $this->isComplete = $last !== null && $last->role === LLMMessageRole::assistant && $last->toolCalls?->isEmpty !== false;
    }
}
