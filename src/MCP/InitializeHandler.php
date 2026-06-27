<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\Foundation\ProcessInfo;
use Sabatier\Service\Application;
use Sabatier\Service\LLMContextProvider;
use Sabatier\Service\MCP\Response\InitializeResult;
use Sabatier\Service\MCP\Response\ServerCapabilities;
use Sabatier\Service\MCP\Response\ServerInfo;
use Sabatier\Service\MCP\Response\ToolsCapability;
use const Sabatier\Service\MCPServerNameDefault;
use const Sabatier\Service\MCPServerNameKey;
use const Sabatier\Service\MCPServerVersionDefault;
use const Sabatier\Service\MCPServerVersionKey;

/** @internal */
final class InitializeHandler
{
    private string $instructions {
        get => $this->instructions ??= new MCPInstructionsProvider()->build();
    }

    public function handle(/** @noinspection PhpUnusedParameterInspection */ RPCMessage $message): InitializeResult
    {
        $environment = ProcessInfo::processInfo()->environment;
        $instructions = $this->instructions;
        $user = Application::shared()->authenticationManager->authentication->authenticatedUser;
        if ($user instanceof LLMContextProvider) {
            $instructions .= "\n\n$user->llmContext";
        }
        return new InitializeResult(new ServerCapabilities(new ToolsCapability(false)), $instructions, new ServerInfo($environment[MCPServerNameKey] ?? MCPServerNameDefault, $environment[MCPServerVersionKey] ?? MCPServerVersionDefault));
    }
}
