<?php

declare(strict_types=1);

namespace Sabatier\Service;

use const Sabatier\Service\MCPSessionHeader;

/**
 * Writes the `Mcp-Session-Id` header on the response to an MCP handshake.
 *
 * The identifier is produced while handling `initialize` and reaches this transformer
 * through the context. Every later request must echo the header back, so this response is
 * the only chance the client has to learn it. Responses that establish no session — every
 * method other than the handshake — pass through untouched.
 *
 * @see MCP\InitializeHandler
 * @see MCP\MCPTransportGuard
 */
final class MCPSessionTransformer extends ResponseTransformer
{
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        if ($identifier = $context->mcpSessionIdentifier) {
            $headers = $response->allHeaderFields;
            $headers[MCPSessionHeader] = $identifier;
        }
        parent::__construct($response, $context);
    }
}
