<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;

/**
 * A per-process, in-memory MCP session store intended for testing and development.
 *
 * Sessions are stored in a static array and are shared within a single PHP process but
 * not across workers or requests in standard FPM deployments. This makes it unsuitable
 * for production use.
 *
 * Use this store in unit tests where isolation and predictable behavior are needed.
 *
 * @see MCPSessionStore
 * @see APCuMCPSessionStore
 */
final class InMemoryMCPSessionStore implements MCPSessionStore
{
    /** @var array<string, array{session: MCPSession, expiresAt: int}> */
    private static array $entries = [];

    #[Override]
    public function get(string $key): ?MCPSession
    {
        $entry = self::$entries[$key] ?? null;
        if ($entry === null || $entry["expiresAt"] <= time()) {
            unset(self::$entries[$key]);
            return null;
        }
        return $entry["session"];
    }

    #[Override]
    public function store(string $key, MCPSession $session, int $ttl): void
    {
        self::$entries[$key] = ["session" => $session, "expiresAt" => time() + $ttl];
    }

    #[Override]
    public function delete(string $key): void
    {
        unset(self::$entries[$key]);
    }

    public static function reset(): void
    {
        self::$entries = [];
    }
}
