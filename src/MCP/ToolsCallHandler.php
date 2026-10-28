<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ToolCallResult;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Throwable;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;
use const Sabatier\Service\MCPProtocolVersionLatestStable;
use const Sabatier\Service\MCPProtocolVersionLegacy;

/** @internal */
final class ToolsCallHandler
{
    /** @var bool Whether the negotiated revision defines `structuredContent`. */
    private bool $carriesStructuredContent {
        get => $this->carriesStructuredContent ??= $this->protocolVersion !== MCPProtocolVersionLegacy;
    }

    /**
     * @param ToolRegistry $registry The catalogue the call is dispatched against.
     * @param string $protocolVersion The revision the session negotiated, which decides whether a structured payload can be carried.
     */
    public function __construct(private readonly ToolRegistry $registry, private readonly string $protocolVersion = MCPProtocolVersionLatestStable)
    {
    }

    /**
     * The structured payload is dropped under the 2025-03-26 revision, which does not define it: a client validating against that revision would reject the envelope.
     *
     * @param RPCMessage $message
     * @return ToolCallResult|JSONRPCError
     * @throws Throwable
     */
    public function handle(RPCMessage $message): ToolCallResult|JSONRPCError
    {
        if (!($name = $message->params["name"])) {
            return new JSONRPCError(JSONRPCErrorDomain, JSONRPCErrorCodeInvalidParams, new Dictionary([LocalizedFailureReasonErrorKey => "Name is required"]));
        }
        /** @var Dictionary<mixed> $arguments */
        $arguments = $message->params["arguments"] ?? new Dictionary();
        $result = $this->registry->call($name, $arguments);
        return new ToolCallResult($result->content, $result->isError, $this->carriesStructuredContent ? $result->structuredContent : null);
    }
}
