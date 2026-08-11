<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Locale;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\ProcessInfo;
use const Sabatier\Service\MCPInstructionsFilenameDefault;
use const Sabatier\Service\MCPInstructionsFilenameKey;

/**
 * Single source of truth for the LLM instruction preamble.
 *
 * Composes the framework-level invariant rules ({@see BaseInstructionsBuilder})
 * with the app-supplied, localized domain instructions loaded from the main
 * bundle.
 *
 * Used by `InitializeHandler` for the MCP `initialize` handshake and by any
 * in-process LLM agent (e.g. an editor chat) that needs the same instructions,
 * so the two never drift apart. Each surface reads its own bundle resource:
 * pass its filename to the constructor, or leave it null to take the
 * {@see MCPInstructionsFilenameKey} environment variable and, failing that,
 * {@see MCPInstructionsFilenameDefault}.
 */
final class MCPInstructionsProvider
{
    /**
     * @param string|null $filename Bundle resource holding the app's domain instructions. Null resolves the MCP server's own.
     */
    public function __construct(private readonly ?string $filename = null)
    {
    }

    private string $instructions {
        get => $this->instructions ??= new BaseInstructionsBuilder()->build($this->appInstructions());
    }

    public function build(): string
    {
        return $this->instructions;
    }

    /**
     * Loads the app's domain instructions, preferring the current language and
     * falling back to the unlocalized resource.
     *
     * A bundle lookup with a localization only ever looks inside that language's
     * directory, so an app that ships its instructions unlocalized — or one running
     * under a language it has no directory for — would silently contribute nothing
     * and leave the model with the framework rules alone. Retrying without the
     * localization keeps `Resources/<lang>/` as the preferred location and the
     * bundle root as the fallback.
     */
    private function appInstructions(): ?string
    {
        $filename = $this->filename ?? ProcessInfo::processInfo()->environment[MCPInstructionsFilenameKey] ?? MCPInstructionsFilenameDefault;
        $bundle = Bundle::main();
        $url = $bundle->url($filename, localization: Locale::getPrimaryLanguage(Locale::getDefault())) ?? $bundle->url($filename);
        return $url !== null ? FileManager::default()->contents($url->path) : null;
    }
}
