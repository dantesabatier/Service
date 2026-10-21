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
        get => $this->protocolVersion !== MCPProtocolVersionLegacy;
    }

    /**
     * @param ToolRegistry $registry The catalogue the call is dispatched against.
     * @param string $protocolVersion The revision the session negotiated, which decides whether a structured payload can be carried.
     */
    public function __construct(private readonly ToolRegistry $registry, private readonly string $protocolVersion = MCPProtocolVersionLatestStable)
    {
    }

    /**
     * Dispatches a `tools/call` request to the registry and shapes the outcome into the MCP envelope.
     *
     * A correctable LLM mistake returns from the registry as a failed `ToolResult` and is carried,
     * per the MCP spec, as a result with `isError` — actionable content the model can fix — not a
     * JSON-RPC protocol error. A real program fault propagates past the registry and is caught once
     * in `MCPRequestHandler` as an Internal Error envelope. Only a missing tool name, a malformed
     * request, is reported here as a JSON-RPC `InvalidParams` error.
     *
     * A structured payload is dropped when the session negotiated the 2025-03-26 revision, which does not define the
     * field: a client validating the envelope against the revision it asked for would reject it.
     *
     * @param RPCMessage $message The parsed `tools/call` request carrying the tool name and arguments in its params.
     * @return ToolCallResult|JSONRPCError The tool result (success or `isError`), or an `InvalidParams` error if the tool name is missing.
     * @throws Throwable A fatal program fault raised by the tool, left to propagate to `MCPRequestHandler`.
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
