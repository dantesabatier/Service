<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\Foundation\Dictionary;

/** Sends MCP JSON-RPC messages independently of the protocol state maintained by {@see MCPClient}. */
interface MCPTransport
{
    /**
     * Sends one MCP message and returns its unparsed response.
     *
     * @param Dictionary<mixed> $message The JSON-RPC request or notification.
     * @param Dictionary<string> $headers Session and protocol headers selected by the client.
     * @param float|null $timeout Seconds available for the exchange, or `null` for the transport default.
     * @return MCPClientResponse The complete transport response.
     * @throws MCPClientException When the transport cannot complete the exchange.
     */
    public function send(Dictionary $message, Dictionary $headers, ?float $timeout = null): MCPClientResponse;
}
