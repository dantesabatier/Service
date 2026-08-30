<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

/**
 * The JSON-RPC method names the MCP endpoint answers.
 *
 * @internal
 */
final readonly class MCPMethod
{
    public const string initialize = "initialize";
    public const string ping = "ping";
    public const string initialized = "notifications/initialized";
    public const string toolsList = "tools/list";
    public const string toolsCall = "tools/call";

    /**
     * Returns whether the given method may be invoked before a session exists.
     *
     * The handshake itself cannot carry a session, since the server issues it in reply.
     * Ping is exempt because the specification allows it before initialization completes,
     * and a body with no method at all is left to the parser to reject on its own terms.
     *
     * @param string|null $method The method being invoked, or null when the body carries none.
     */
    public static function isAllowedWithoutSession(?string $method): bool
    {
        return in_array($method, [null, self::initialize, self::ping], true);
    }
}
