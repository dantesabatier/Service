<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\MCP\Response\ToolDescriptor;

/** Builds the provider-neutral messages and system prompt sent on each model turn. */
interface LLMContextAssembler
{
    /**
     * Assembles one context without mutating the caller's history.
     *
     * @param ArrayClass<LLMMessage> $messages The complete conversation history available to the run.
     * @param ArrayClass<ToolDescriptor> $tools The tools advertised on this turn.
     * @param string|null $systemPrompt The inherited system prompt, or `null` for none.
     */
    public function assemble(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): LLMContext;
}
