<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * An immutable record of an initialized MCP session.
 *
 * Created when a client completes the `initialize` handshake and persisted through
 * `MCPSessionStore` for the lifetime of the session. It records what the handshake
 * agreed on, so later requests are answered under the same terms.
 *
 * A session marks that the handshake happened; it never authenticates. Every request
 * carries its own credentials and is evaluated by the access evaluator chain, whether
 * or not it presents a session identifier.
 *
 * @see MCPSessionStore
 * @see MCP\InitializeHandler
 */
final readonly class MCPSession
{
    /**
     * @param string $subject The username of the identity that performed the handshake. The session is bound to it, so a session identifier alone cannot reach another user's data.
     * @param string $protocolVersion The protocol version negotiated during initialization.
     * @param string $clientName The name the client declared in `clientInfo`, or an empty string when it declared none. Recorded for logging; a client writes it freely and it never grants access.
     * @param string $clientVersion The version the client declared in `clientInfo`, or an empty string when it declared none.
     */
    public function __construct(public string $subject, public string $protocolVersion, public string $clientName = "", public string $clientVersion = "")
    {
    }
}
