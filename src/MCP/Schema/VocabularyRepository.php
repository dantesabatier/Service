<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Schema;

use Locale;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\ProcessInfo;
use const Sabatier\Service\MCPVocabularyFilenameDefault;
use const Sabatier\Service\MCPVocabularyFilenameKey;

/**
 * Loads the localized vocabulary file that enriches the model schema.
 *
 * Resolves the vocabulary JSON bundle resource for the current locale and returns
 * its contents. The filename can be overridden via the `MCPVocabularyFilenameKey`
 * environment variable.
 */
final class VocabularyRepository
{
    public function load(): array
    {
        $filename = ProcessInfo::processInfo()->environment[MCPVocabularyFilenameKey] ?? MCPVocabularyFilenameDefault;
        if (!($url = Bundle::main()->url($filename, localization: Locale::getPrimaryLanguage(Locale::getDefault())))) {
            return [];
        }
        if (!($contents = FileManager::default()->contents($url->path))) {
            return [];
        }
        return json_decode($contents, true) ?? [];
    }
}
