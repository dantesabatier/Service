<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Response;

use JsonSerializable;
use Override;
use const Sabatier\Service\MCPProtocolVersionLegacy;
use const Sabatier\Service\MCPServerNameDefault;
use const Sabatier\Service\MCPServerVersionDefault;

final readonly class InitializeResult implements JsonSerializable
{
    public function __construct(public ServerCapabilities $capabilities, public string $instructions, public ServerInfo $serverInfo = new ServerInfo(MCPServerNameDefault, MCPServerVersionDefault), public string $protocolVersion = MCPProtocolVersionLegacy)
    {
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ["capabilities" => $this->capabilities, "instructions" => $this->instructions, "serverInfo" => $this->serverInfo, "protocolVersion" => $this->protocolVersion];
    }
}
