<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/**
 * Receives the agentic loop's progress as it happens.
 *
 * `LLMRun` reports what a finished run cost and why it stopped, which answers the question
 * afterwards. This interface answers it while the run is still going, and reports what the return
 * value cannot express at all: which iteration a tool call belonged to, how long it took, whether
 * it was served from the cache instead of executed, and which subagent did the work. Only the loop
 * sees those boundaries — from outside, a run is one blocking call.
 *
 * Nothing here decides what the events are for. Persisting them, logging them, counting them or
 * streaming them to a browser is the application's. An observer that cares about one event
 * implements the rest as empty bodies, which is the same shape `ApplicationDelegate` asks of an
 * application that only wants one of its callbacks.
 *
 * Every callback carries the run identifier, and a subagent reports under its own identifier while
 * naming its parent, so a nested run's work stays attributable to the run that launched it rather
 * than appearing as unrelated activity.
 *
 * The loop calls an observer synchronously and does not guard against it throwing: an observer
 * that fails takes the run down with it. Keep the work small and let it not raise — the run is not
 * the place to discover that a log sink is unreachable.
 */
interface LLMRunObserver
{
    /**
     * Called once before the first model turn.
     *
     * @param LLMRunContext $context Identifies the run and the parent that launched it, when it is a subagent.
     */
    public function runWillStart(LLMRunContext $context): void;

    /**
     * Called before each model turn, after the context has been assembled and every budget has
     * admitted the turn.
     *
     * @param LLMRunContext $context Identifies the run.
     * @param int $iteration The turn about to be sent, counting from one.
     * @param LLMContext $assembled The messages and system prompt going to the provider, including how much history was dropped.
     */
    public function iterationWillStart(LLMRunContext $context, int $iteration, LLMContext $assembled): void;

    /**
     * Called after each model turn returns, before its tool calls are handled.
     *
     * @param LLMRunContext $context Identifies the run.
     * @param int $iteration The turn that just completed, counting from one.
     * @param LLMTurn $turn What the provider answered, including its tokens and stop reason.
     */
    public function iterationDidEnd(LLMRunContext $context, int $iteration, LLMTurn $turn): void;

    /**
     * Called once per tool call the loop resolves, whatever resolved it.
     *
     * A call served from the run's cache reports `$duration` of zero and `$wasCached` true: it is
     * the event that explains why a tool the model asked for never reached the registry.
     *
     * @param LLMRunContext $context Identifies the run.
     * @param int $iteration The turn whose tool calls are being handled, counting from one.
     * @param LLMToolCall $call The invocation the model requested.
     * @param string $result The text fed back to the model.
     * @param bool $isError Whether the call failed with a correctable error.
     * @param bool $wasCached Whether the run's cache answered instead of the tool.
     * @param float $duration Seconds the call took, or zero when the cache answered.
     */
    public function toolCallDidEnd(LLMRunContext $context, int $iteration, LLMToolCall $call, string $result, bool $isError, bool $wasCached, float $duration): void;

    /**
     * Called once when a run ends, whatever ended it.
     *
     * @param LLMRunContext $context Identifies the run.
     * @param LLMRun $run The messages generated, the tokens consumed and why the loop stopped.
     */
    public function runDidEnd(LLMRunContext $context, LLMRun $run): void;
}
