<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Redis;

/**
 * A Redis-backed MCP session store for distributed deployments.
 *
 * Uses `SETEX` to store serialized sessions with a TTL, `GET` to retrieve them and `DEL`
 * to end them. Suitable for multiserver deployments where all workers share the same
 * Redis instance, so a session established on one server is honored by the rest.
 *
 * Requires the `ext-redis` PHP extension and an injected `Redis` connection.
 *
 * <code>
 * Application::shared()->mcpSessionStore = new RedisMCPSessionStore();
 * </code>
 *
 * @see MCPSessionStore
 * @see Application::$mcpSessionStore
 */
final readonly class RedisMCPSessionStore implements MCPSessionStore
{
    private Redis $redis;

    public function __construct(string $host = "127.0.0.1", int $port = 6379)
    {
        $this->redis = new Redis();
        $this->redis->pconnect($host, $port);
    }

    #[Override]
    public function get(string $key): ?MCPSession
    {
        $value = $this->redis->get($key);
        if (!is_string($value)) {
            return null;
        }
        /** @var MCPSession $result */
        $result = unserialize($value);
        return $result;
    }

    #[Override]
    public function store(string $key, MCPSession $session, int $ttl): void
    {
        $this->redis->setex($key, $ttl, serialize($session));
    }

    #[Override]
    public function delete(string $key): void
    {
        $this->redis->del($key);
    }
}
