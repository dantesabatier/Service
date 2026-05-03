<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\Foundation\Dictionary;
use Sabatier\Service\Request;

final readonly class JSONRPCRequestParser
{
    public function __construct(private JSONRPCRequestPruner $pruner = new JSONRPCRequestPruner())
    {
    }

    public function parse(Request $request): RPCMessage|JSONRPCError|null
    {
        $parameters = clone $request->parameters;
        $this->pruner->prune($parameters);
        if ($parameters->isEmpty) {
            return new JSONRPCError(JSONRPCErrorDomain, JSONRPCErrorCodeParseErrorCode);
        }
        if (!($method = $parameters["method"])) {
            return new JSONRPCError(JSONRPCErrorDomain, JSONRPCErrorCodeInvalidRequest);
        }
        if (!isset($parameters["id"])) {
            return null;
        }
        /** @var Dictionary<mixed> $params */
        $params = $parameters["params"] ?? new Dictionary();
        $this->pruner->prune($params);
        return new RPCMessage($parameters["id"], $method, $params);
    }
}
