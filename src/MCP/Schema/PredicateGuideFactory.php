<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Schema;

use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\ProcessInfo;
use const Sabatier\Service\MCPPredicateExamplesFilenameDefault;
use const Sabatier\Service\MCPPredicateExamplesFilenameKey;

final readonly class PredicateGuideFactory
{
    public function make(): PredicateGuide
    {
        return new PredicateGuide(
            [
                "%K" => "Key path — use dot notation to traverse relationships (e.g. \"area.name\", \"category.name\")",
                "%s" => "String, boolean, or enum value",
                "%d" => "Integer",
                "%f" => "Float",
            ],
            [
                "=", "!=", "<", ">", "<=", ">=",
                "CONTAINS[cd]", "LIKE[cd]", "IN", "BETWEEN",
                "AND", "OR", "NOT",
            ],
            $this->loadExamples()
        );
    }

    private function loadExamples(): array
    {
        $filename = ProcessInfo::processInfo()->environment[MCPPredicateExamplesFilenameKey] ?? MCPPredicateExamplesFilenameDefault;
        if (!($url = Bundle::main()->url($filename))) {
            return [];
        }
        if (!($contents = FileManager::default()->contents($url->path))) {
            return [];
        }
        return json_decode($contents, true) ?? [];
    }
}
