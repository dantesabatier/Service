<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Throwable;

/** Runs one replaceable orchestration strategy and owns policy enforcement and trace emission for the services it consumes. */
interface LLMAgentLoop
{
    /**
     * Executes one agent run without mutating the caller's original conversation.
     *
     * @param LLMAgentRunRequest $request The disposable input snapshot and inherited system prompt.
     * @param LLMAgentEnvironment $environment The provider, tools, policies and infrastructure available to the strategy.
     * @throws Throwable A fatal program fault raised while orchestrating the run.
     */
    public function run(LLMAgentRunRequest $request, LLMAgentEnvironment $environment): LLMRun;
}
