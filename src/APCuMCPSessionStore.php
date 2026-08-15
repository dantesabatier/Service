<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;

/**
 * An APCu-backed MCP session store shared across PHP workers on the same server.
 *
 * This is the default `MCPSessionStore` implementation. It uses APCu shared memory to
 * persist sessions across requests, visible to all worker processes on the same machine.
 *
 * Requires the APCu PHP extension (`ext-apcu`). For multiserver deployments, replace this
 * with a distributed store via `Application::$mcpSessionStore`; a session established on
 * one server is otherwise unknown to the others.
 *
 * @see MCPSessionStore
 */
final class APCuMCPSessionStore implements MCPSessionStore
{
    #[Override]
    public function get(string $key): ?MCPSession
    {
        $value = apcu_fetch($key, $success);
        if (!$success) {
            return null;
        }
        /** @var MCPSession $result */
        $result = unserialize((string)$value);
        return $result;
    }

    #[Override]
    public function store(string $key, MCPSession $session, int $ttl): void
    {
        apcu_store($key, serialize($session), $ttl);
    }

    #[Override]
    public function delete(string $key): void
    {
        apcu_delete($key);
    }
}
