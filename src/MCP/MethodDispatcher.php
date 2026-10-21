<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\Foundation\Dictionary;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;

/** @internal */
final readonly class MethodDispatcher
{
    public function __construct(private InitializeHandler $initialize, private ToolsListHandler $toolsList, private ToolsCallHandler $toolsCall, private ResourcesListHandler $resourcesList, private ResourcesReadHandler $resourcesRead)
    {
    }

    public function dispatch(RPCMessage $message): mixed
    {
        return match ($message->method) {
            "initialize" => $this->initialize->handle($message),
            "tools/list" => $this->toolsList->handle($message),
            "tools/call" => $this->toolsCall->handle($message),
            "resources/list" => $this->resourcesList->handle($message),
            "resources/read" => $this->resourcesRead->handle($message),
            "ping", "notifications/initialized" => null,
            default => new JSONRPCError(JSONRPCErrorDomain, JSONRPCErrorCodeMethodNotFound, new Dictionary([LocalizedFailureReasonErrorKey => "Method not found: $message->method"]))
        };
    }
}
