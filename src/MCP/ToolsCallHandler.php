<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Response\ToolCallResult;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Throwable;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;

/** @internal */
final readonly class ToolsCallHandler
{
    public function __construct(private ToolRegistry $registry)
    {
    }

    public function handle(RPCMessage $message): ToolCallResult|JSONRPCError
    {
        if (!($name = $message->params["name"])) {
            return new JSONRPCError(JSONRPCErrorDomain, JSONRPCErrorCodeInvalidParams, new Dictionary([LocalizedFailureReasonErrorKey => "Name is required"]));
        }
        /** @var Dictionary<mixed> $arguments */
        $arguments = $message->params["arguments"] ?? new Dictionary();
        try {
            return new ToolCallResult($this->registry->call($name, $arguments));
        } catch (Throwable $throwable) {
            // A tool that throws is reported back to the model as a tool result with
            // isError: true (per the MCP spec), not as a JSON-RPC protocol error, so the
            // model receives the message as actionable content and can correct its call.
            error_log((string)$throwable);
            return new ToolCallResult(new ArrayClass([new ContentItem("text", $throwable->getMessage())]), isError: true);
        }
    }
}
