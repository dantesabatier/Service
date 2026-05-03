<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ToolCallResult;
use Sabatier\Service\MCP\Tools\ToolRegistry;
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
        return new ToolCallResult($this->registry->call($name, $arguments));
    }
}
