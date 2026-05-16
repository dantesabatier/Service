<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Schema;

use Locale;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\ProcessInfo;
use const Sabatier\Service\MCPPredicateExamplesFilenameDefault;
use const Sabatier\Service\MCPPredicateExamplesFilenameKey;

/**
 * Builds the `PredicateGuide` included in the model schema.
 *
 * Provides the LLM with the format specifiers, comparison operators, and
 * predicate examples it needs to construct valid fetch predicates. Examples
 * are loaded from a localized JSON bundle resource; the filename can be
 * overridden via the `MCPPredicateExamplesFilenameKey` environment variable.
 */
final readonly class PredicateGuideFactory
{
    public function make(): PredicateGuide
    {
        return new PredicateGuide(
            [
                "%K" => "Key path — use dot notation to traverse relationships (e.g. \"area.name\", \"category.name\")",
                "%s" => "String, boolean, string enum value, or array (for IN and BETWEEN). For BETWEEN pass a two-element array: predicate=\"%K BETWEEN %s\", arguments=[\"creationDate\", [\"2025-01-01\", \"2025-01-31\"]]",
                "%d" => "Integer or integer enum value",
                "%f" => "Float",
            ],
            [
                "=", "!=", "<", ">", "<=", ">=",
                "CONTAINS[cd]", "LIKE[cd]", "IN", "BETWEEN",
                "AND", "OR", "NOT",
            ],
            [...$this->baseExamples(), ...$this->loadExamples()]
        );
    }

    /**
     * @return list<string>
     */
    private function baseExamples(): array
    {
        return [
            "Equality (string): predicate=\"%K = %s\", arguments=[\"name\", \"Acme\"]",
            "Equality (integer enum — pass the mapped value, never the case name): predicate=\"%K = %d\", arguments=[\"status\", 2]",
            "Equality (string enum — pass the mapped value, never the case name): predicate=\"%K = %s\", arguments=[\"status\", \"active\"]",
            "Comparison: predicate=\"%K > %d\", arguments=[\"total\", 1000]",
            "Date range with BETWEEN (preferred — key path appears once): predicate=\"%K BETWEEN %@\", arguments=[\"creationDate\", [\"2025-01-01\", \"2025-01-31\"]]",
            "Date range with AND (key path must be repeated for each %K): predicate=\"%K >= %s AND %K < %s\", arguments=[\"creationDate\", \"2025-01-01\", \"creationDate\", \"2025-02-01\"]",
            "Relationship: predicate=\"%K = %s\", arguments=[\"customer.name\", \"Acme\"]",
            "IN list: predicate=\"%K IN %@\", arguments=[\"status\", [\"active\", \"pending\"]]",
        ];
    }

    /**
     * @return list<string>
     */
    private function loadExamples(): array
    {
        $filename = ProcessInfo::processInfo()->environment[MCPPredicateExamplesFilenameKey] ?? MCPPredicateExamplesFilenameDefault;
        if (!($url = Bundle::main()->url($filename, localization: Locale::getPrimaryLanguage(Locale::getDefault())))) {
            return [];
        }
        if (!($contents = FileManager::default()->contents($url->path))) {
            return [];
        }
        return json_decode($contents, true) ?? [];
    }
}
