<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Throwable;

/** Runs one replaceable orchestration strategy through the framework's guarded agent session. */
interface LLMAgentLoop
{
    /**
     * Executes one agent run.
     *
     * @param LLMAgentSession $session The only gateway to context assembly, model turns, tools, subagents, budgets, deadlines and traces.
     * @return LLMAgentLoopOutcome The strategy's terminal decision; the runtime constructs the aggregate run.
     * @throws Throwable A fatal program fault raised while orchestrating the run.
     */
    public function run(LLMAgentSession $session): LLMAgentLoopOutcome;
}
