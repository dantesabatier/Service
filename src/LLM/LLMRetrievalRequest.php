<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;

/** The latest user query together with a disposable snapshot of its conversation history. */
final readonly class LLMRetrievalRequest
{
    /**
     * @param string $query The latest non-empty user message, trimmed for direct retrieval.
     * @param ArrayClass<LLMMessage> $messages A cloned history the retriever may inspect or rewrite without mutating the agent's conversation.
     */
    public function __construct(public string $query, public ArrayClass $messages)
    {
    }
}
