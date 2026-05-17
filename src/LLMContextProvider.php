<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * Opt-in contract for authenticated entities that want to expose identity context
 * to connected LLM clients.
 *
 * When the authenticated entity implements this interface, `InitializeHandler`
 * automatically appends the returned string to the MCP server instructions so
 * every LLM client receives it as part of its initial context.
 *
 * @see InitializeHandler
 */
interface LLMContextProvider
{
    /**
     * A plain-text description of the current user for injection into the LLM
     * system prompt. Keep it concise — one or two lines at most.
     *
     * Example: "User: Jane Doe, objectID: 42"
     */
    public string $llmContext {
        get;
    }
}
