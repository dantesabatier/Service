<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Locale;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\ProcessInfo;
use const Sabatier\Service\MCPVocabularyFilenameDefault;
use const Sabatier\Service\MCPVocabularyFilenameKey;

/**
 * Resolves the localized display title and description of a tool from the vocabulary of the
 * bundle that owns the tool's class.
 *
 * A tool is localized against its OWN bundle — the framework bundle for built-in tools, the
 * application bundle for custom ones — never `Bundle::main()`. Because each tool reads the
 * `"tools"` section of the `mcp_vocabulary.json` shipped in its own bundle, a framework tool and
 * an application tool can never share or overwrite each other's localizations: they read
 * physically separate files. An entry for a tool that does not live in the bundle is simply never
 * consulted.
 *
 * The English text lives in the bundle's base `en` vocabulary, which is the source of truth and
 * the fallback: a lookup resolves against the current locale first, then falls back to the `en`
 * entry of the same bundle. A tool with no entry even in `en` yields `null`, so a display name
 * degrades to the technical `name` and a description to the `name` itself rather than failing.
 *
 * @see \Sabatier\Service\MCP\Schema\VocabularyRepository the equivalent loader for the model schema.
 */
final class ToolVocabulary
{
    private const string BaseLanguage = "en";

    /** @var array<string, ToolVocabulary> Cache keyed by bundle resource path, so each bundle's files are read once. */
    private static array $cache = [];

    /** @var array<string, mixed> The `"tools"` section resolved for the current locale. */
    private readonly array $tools;
    /** @var array<string, mixed> The `"tools"` section of the base `en` vocabulary, used as fallback. */
    private readonly array $baseTools;

    private function __construct(array $localized, array $base)
    {
        $this->tools = $localized["tools"] ?? [];
        $this->baseTools = $base["tools"] ?? [];
    }

    /**
     * Loads the tool vocabulary shipped in the given bundle: the current locale's file plus the
     * base `en` file for fallback.
     *
     * @param Bundle $bundle The bundle that owns the tool being localized.
     */
    public static function forBundle(Bundle $bundle): self
    {
        $language = Locale::getPrimaryLanguage(Locale::getDefault()) ?: self::BaseLanguage;
        $key = ($bundle->resourceURL?->path ?? $bundle->bundleURL->path) . "|" . $language;
        return self::$cache[$key] ??= new self(
            self::load($bundle, $language),
            $language === self::BaseLanguage ? [] : self::load($bundle, self::BaseLanguage)
        );
    }

    /**
     * Returns the value of a field for a tool, resolving the current locale first and falling back
     * to the base `en` entry of the same bundle. Returns `null` when neither carries the field, so
     * the caller can degrade to the technical name.
     *
     * @param string $name The tool's technical name (the `"tools"` section key).
     * @param string $field Either `"title"` or `"description"`.
     */
    public function localize(string $name, string $field): ?string
    {
        return $this->tools[$name][$field] ?? $this->baseTools[$name][$field] ?? null;
    }

    private static function load(Bundle $bundle, string $language): array
    {
        $filename = ProcessInfo::processInfo()->environment[MCPVocabularyFilenameKey] ?? MCPVocabularyFilenameDefault;
        if (!($url = $bundle->url($filename, localization: $language))) {
            return [];
        }
        if (!($contents = FileManager::default()->contents($url->path))) {
            return [];
        }
        return json_decode($contents, true) ?? [];
    }
}
