<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * Contract for the backend storage used by the MCP session layer.
 *
 * Implementations store and retrieve sessions keyed by the identity that owns them and
 * the session identifier the server issued (`<subject>:<identifier>`). Binding the key
 * to the subject means a guessed identifier still resolves under its own owner, never
 * another user's. The framework ships with three built-in implementations:
 *
 * - `APCuMCPSessionStore` — shared across PHP workers on the same server via APCu shared
 *   memory. This is the default and requires the APCu extension.
 * - `RedisMCPSessionStore` — suitable for multiserver deployments.
 * - `InMemoryMCPSessionStore` — per-process, in-memory. Useful for testing.
 *
 * @see MCPSession
 * @see Application::$mcpSessionStore
 */
interface MCPSessionStore
{
    /**
     * Returns the stored session for the given key, or null if not found or expired.
     *
     * @param string $key The scoped session key.
     */
    public function get(string $key): ?MCPSession;

    /**
     * Stores a session under the given key for the specified duration.
     *
     * Called again on every request that carries the session, which refreshes the
     * expiry so that only an abandoned session times out.
     *
     * @param string $key The scoped session key.
     * @param MCPSession $session The session to store.
     * @param int $ttl Time-to-live in seconds.
     */
    public function store(string $key, MCPSession $session, int $ttl): void;

    /**
     * Removes the session stored under the given key, if any.
     *
     * @param string $key The scoped session key.
     */
    public function delete(string $key): void;
}
