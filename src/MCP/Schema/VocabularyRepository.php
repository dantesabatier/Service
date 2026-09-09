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
        $bundle = Bundle::main();
        // A localized lookup only ever looks inside that language's directory, so a locale with no directory of its own would leave the schema without aliases or descriptions, silently. Retrying without the localization keeps `Resources/<lang>/` as the preferred location and the bundle root as the fallback.
        if (!($url = $bundle->url($filename, localization: Locale::getPrimaryLanguage(Locale::getDefault())) ?? $bundle->url($filename))) {
            return [];
        }
        if (!($contents = FileManager::default()->contents($url->path))) {
            return [];
        }
        return json_decode($contents, true) ?? [];
    }
}
