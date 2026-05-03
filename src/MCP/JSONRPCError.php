<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use function Sabatier\Foundation\localized_string;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;

/** @internal */
final class JSONRPCError extends Error
{
    #[Override]
    public ?string $localizedFailureReason {
        get => $this->userInfo?->valueForKey(LocalizedFailureReasonErrorKey) ?? match ($this->code) {
            JSONRPCErrorCodeInvalidRequest => localized_string("Invalid Request: The JSON sent is not a valid Request object."),
            JSONRPCErrorCodeMethodNotFound => localized_string("Method not found: The method does not exist / is not available."),
            JSONRPCErrorCodeInvalidParams => localized_string("Invalid params: Invalid method parameter(s)."),
            JSONRPCErrorCodeInternalError => localized_string("Internal error: Internal JSON-RPC error."),
            JSONRPCErrorCodeParseErrorCode => localized_string("Parse error: Invalid JSON was received by the server."),
            default => null,
        };
    }

    #[Override]
    public function jsonSerialize(): Dictionary
    {
        return new Dictionary(["code" => $this->code, "message" => $this->localizedDescription]);
    }
}
