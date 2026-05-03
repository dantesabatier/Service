<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Schema;

use JsonSerializable;
use Override;
use Sabatier\Foundation\Dictionary;

final readonly class ModelSchema implements JsonSerializable
{
    /**
     * @param Dictionary<EntitySchema> $entities
     * @param PredicateGuide $predicateGuide
     */
    public function __construct(public Dictionary $entities, public PredicateGuide $predicateGuide)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["entities" => $this->entities, "predicate_syntax" => $this->predicateGuide];
    }
}
