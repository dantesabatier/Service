<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use Sabatier\Foundation\Set;
use const Sabatier\Service\MCPProtocolVersionLatestStable;
use const Sabatier\Service\MCPProtocolVersionLegacy;
use const Sabatier\Service\MCPProtocolVersionStable;

/**
 * Negotiates the protocol version a session runs under.
 *
 * The client states the version it speaks in the `initialize` request; the server answers
 * with the same one when it implements it, and otherwise with the latest it does. A client
 * that cannot speak the answer disconnects, which is why answering with something the
 * server does not implement would be worse than answering with a version the client
 * did not ask for.
 *
 * @internal
 */
final readonly class MCPProtocolVersion
{
    /**
     * Returns every protocol version this server implements.
     *
     * @return Set<string>
     */
    public static function supported(): Set
    {
        return new Set([MCPProtocolVersionLegacy, MCPProtocolVersionStable, MCPProtocolVersionLatestStable]);
    }

    /**
     * Returns the version to run under given the one the client asked for.
     *
     * @param string|null $requested The version the client declared, or null when it declared none.
     */
    public static function negotiate(?string $requested): string
    {
        if ($requested !== null && self::supported()->containsElement($requested)) {
            return $requested;
        }
        return MCPProtocolVersionLatestStable;
    }
}
