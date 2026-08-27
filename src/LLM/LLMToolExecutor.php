<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use Throwable;

/** Describes and executes the real tools available to an agent, independently of where they run. */
interface LLMToolExecutor
{
    /** @var ArrayClass<ToolDescriptor> Provider-neutral descriptors advertised to the model. */
    public ArrayClass $tools {
        get;
    }

    /**
     * Whether the executor recognizes the requested tool.
     *
     * @param LLMToolCall $call The concrete invocation to look up.
     */
    public function contains(LLMToolCall $call): bool;

    /**
     * Whether the concrete invocation only reads state.
     *
     * @param LLMToolCall $call The invocation whose effect is being classified.
     */
    public function isReadOnly(LLMToolCall $call): bool;

    /**
     * Whether a successful invocation may be reused for the same arguments within one run.
     *
     * @param LLMToolCall $call The invocation whose result may be cached.
     */
    public function isCacheable(LLMToolCall $call): bool;

    /**
     * Executes one invocation that already passed the agent's approval and budget checks.
     *
     * @param LLMToolCall $call The invocation to execute.
     * @return LLMToolExecutionResult Text to feed back to the model and whether it describes a correctable failure.
     * @throws Throwable A fatal executor or tool fault the model cannot correct.
     */
    public function execute(LLMToolCall $call): LLMToolExecutionResult;
}
