<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Locale;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Service\MCP\Response\InitializeResult;
use Sabatier\Service\MCP\Response\ServerCapabilities;
use Sabatier\Service\MCP\Response\ServerInfo;
use Sabatier\Service\MCP\Response\ToolsCapability;
use const Sabatier\Service\MCPInstructionsFilenameDefault;
use const Sabatier\Service\MCPInstructionsFilenameKey;
use const Sabatier\Service\MCPServerNameDefault;
use const Sabatier\Service\MCPServerNameKey;
use const Sabatier\Service\MCPServerVersionDefault;
use const Sabatier\Service\MCPServerVersionKey;

/** @internal */
final class InitializeHandler
{
    private string $instructions {
        get {
            if (isset($this->instructions)) {
                return $this->instructions;
            }
            $appInstructions = null;
            $filename = ProcessInfo::processInfo()->environment[MCPInstructionsFilenameKey] ?? MCPInstructionsFilenameDefault;
            if ($url = Bundle::main()->url($filename, localization: Locale::getPrimaryLanguage(Locale::getDefault()))) {
                $appInstructions = FileManager::default()->contents($url->path);
            }
            return $this->instructions = new BaseInstructionsBuilder()->build($appInstructions);
        }
    }

    public function handle(): InitializeResult
    {
        $environment = ProcessInfo::processInfo()->environment;
        return new InitializeResult(new ServerCapabilities(new ToolsCapability(false)), $this->instructions, new ServerInfo($environment[MCPServerNameKey] ?? MCPServerNameDefault, $environment[MCPServerVersionKey] ?? MCPServerVersionDefault));
    }
}
