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
 * bundle. The bundle filename comes from the {@see MCPInstructionsFilenameKey}
 * environment variable, defaulting to {@see MCPInstructionsFilenameDefault}.
 *
 * Used by `InitializeHandler` for the MCP `initialize` handshake and by any
 * in-process LLM agent (e.g. an editor chat) that needs the same instructions,
 * so the two never drift apart.
 */
final class MCPInstructionsProvider
{
    private string $instructions {
        get => $this->instructions ??= new BaseInstructionsBuilder()->build($this->appInstructions());
    }

    public function build(): string
    {
        return $this->instructions;
    }

    private function appInstructions(): ?string
    {
        $filename = ProcessInfo::processInfo()->environment[MCPInstructionsFilenameKey] ?? MCPInstructionsFilenameDefault;
        if ($url = Bundle::main()->url($filename, localization: Locale::getPrimaryLanguage(Locale::getDefault()))) {
            return FileManager::default()->contents($url->path);
        }
        return null;
    }
}
