<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Throwable;

/** Runs one replaceable orchestration strategy through the framework's protected agent runtime. */
interface LLMAgentLoop
{
    /**
     * Executes one agent run.
     *
     * @param LLMAgentRuntime $runtime The only gateway to context assembly, model turns, tools, budgets, deadlines and traces.
     * @throws Throwable A fatal program fault raised while orchestrating the run.
     */
    public function run(LLMAgentRuntime $runtime): LLMRun;
}
