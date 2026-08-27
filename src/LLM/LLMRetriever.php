<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;
use Throwable;

/** Retrieves provider-neutral external knowledge without prescribing a search or vector backend. Each context assembly may call it again as conversation evidence changes, so the implementation owns freshness and caching. */
interface LLMRetriever
{
    /**
     * Retrieves documents for one user query in descending relevance order.
     *
     * @param LLMRetrievalRequest $request The latest query and a disposable conversation snapshot.
     * @return ArrayClass<LLMRetrievedDocument> Ranked reference documents; an empty collection means no relevant context was found.
     * @throws Throwable A retrieval failure the caller must handle rather than silently answering without required knowledge.
     */
    public function retrieve(LLMRetrievalRequest $request): ArrayClass;
}
