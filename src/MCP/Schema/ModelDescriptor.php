<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Schema;

/** @internal */
final class ModelDescriptor
{
    private ModelSchema $schema {
        get => $this->schema ??= new ModelSchema($this->localizer->apply($this->extractor->extract(), $this->vocabulary->load()), $this->predicateGuide->make());
    }

    public function __construct(private readonly ModelSchemaExtractor $extractor, private readonly VocabularyRepository $vocabulary, private readonly SchemaLocalizer $localizer, private readonly PredicateGuideFactory $predicateGuide)
    {
    }

    public function describe(): ModelSchema
    {
        return $this->schema;
    }
}
