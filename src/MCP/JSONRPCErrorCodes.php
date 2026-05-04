<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

/** @internal @var int JSON-RPC error code for an invalid request object. */
const JSONRPCErrorCodeInvalidRequest = -32600;
/** @internal @var int JSON-RPC error code for a method that does not exist or is not available. */
const JSONRPCErrorCodeMethodNotFound = -32601;
/** @internal @var int JSON-RPC error code for invalid method parameters. */
const JSONRPCErrorCodeInvalidParams = -32602;
/** @internal @var int JSON-RPC error code for an internal JSON-RPC error. */
const JSONRPCErrorCodeInternalError = -32603;
/** @internal @var int JSON-RPC error code for a parse error (invalid JSON). */
const JSONRPCErrorCodeParseErrorCode = -32700;
