<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Service\Application;
use Sabatier\Service\LLMContextProvider;
use Sabatier\Service\MCP\Response\InitializeResult;
use Sabatier\Service\MCP\Response\ServerCapabilities;
use Sabatier\Service\MCP\Response\ServerInfo;
use Sabatier\Service\MCP\Response\ToolsCapability;
use Sabatier\Service\MCPSession;
use const Sabatier\Service\MCPServerNameDefault;
use const Sabatier\Service\MCPServerNameKey;
use const Sabatier\Service\MCPServerVersionDefault;
use const Sabatier\Service\MCPServerVersionKey;

/** @internal */
final class InitializeHandler
{
    /** @var string|null The identifier of the session established by the last handled handshake, read by the responder to return it to the client. */
    private(set) ?string $sessionIdentifier = null;

    private string $instructions {
        get => $this->instructions ??= new MCPInstructionsProvider()->build();
    }

    /**
     * @param MCPSessionKey $sessionKey Derives the storage key of the session this handshake establishes and issues its identifier.
     */
    public function __construct(private readonly MCPSessionKey $sessionKey = new MCPSessionKey())
    {
    }

    public function handle(RPCMessage $message): InitializeResult
    {
        $environment = ProcessInfo::processInfo()->environment;
        $instructions = $this->instructions;
        $user = Application::shared()->authenticationManager->authentication->authenticatedUser;
        if ($user instanceof LLMContextProvider) {
            $instructions .= "\n\n$user->llmContext";
        }
        $protocolVersion = MCPProtocolVersion::negotiate($this->stringValue($message->params, "protocolVersion"));
        $this->sessionIdentifier = $this->establishSession($message, $user?->username ?? "", $protocolVersion);
        return new InitializeResult(new ServerCapabilities(new ToolsCapability(false)), $instructions, new ServerInfo($environment[MCPServerNameKey] ?? MCPServerNameDefault, $environment[MCPServerVersionKey] ?? MCPServerVersionDefault), $protocolVersion);
    }

    private function establishSession(RPCMessage $message, string $subject, string $protocolVersion): string
    {
        /** @var Dictionary<mixed>|null $clientInfo */
        $clientInfo = $message->params["clientInfo"] instanceof Dictionary ? $message->params["clientInfo"] : null;
        $identifier = $this->sessionKey->identifier();
        Application::shared()->mcpSessionStore->store($this->sessionKey->key($subject, $identifier), new MCPSession($subject, $protocolVersion, $this->stringValue($clientInfo, "name") ?? "", $this->stringValue($clientInfo, "version") ?? ""), $this->sessionKey->timeToLive);
        return $identifier;
    }

    /**
     * @param Dictionary<mixed>|null $dictionary
     */
    private function stringValue(?Dictionary $dictionary, string $key): ?string
    {
        $value = $dictionary?->valueForKey($key);
        return is_string($value) ? $value : null;
    }
}
