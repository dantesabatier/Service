<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

/** One ranked piece of external knowledge returned for retrieval augmentation. */
final readonly class LLMRetrievedDocument
{
    /**
     * @param string $content The untrusted reference text retrieved from external storage.
     * @param string|null $identifier A stable document or chunk identifier, or `null` when the source has none.
     * @param string|null $source A human-readable source name or URI, or `null` when unavailable.
     * @param string|null $title A display title, or `null` when unavailable.
     * @param float|null $score The retriever-specific relevance score, or `null` when it does not expose one.
     */
    public function __construct(public string $content, public ?string $identifier = null, public ?string $source = null, public ?string $title = null, public ?float $score = null)
    {
    }
}
