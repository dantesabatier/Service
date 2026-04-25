<?php

namespace Sabatier\Service;

use Override;

/**
 * A per-process, in-memory idempotency store intended for testing and development.
 *
 * Response snapshots are stored in a static array and are shared within a single PHP
 * process but not across workers or requests in standard FPM deployments. This makes it
 * unsuitable for production use.
 *
 * Use this store in unit tests where isolation and predictable behavior are needed.
 *
 * @see IdempotencyStore
 * @see APCuIdempotencyStore
 */
final class InMemoryIdempotencyStore implements IdempotencyStore
{
    /** @var array<string, array{response: IdempotentResponse, expiresAt: int}> */
    private static array $entries = [];

    #[Override]
    public function get(string $key): ?IdempotentResponse
    {
        $entry = self::$entries[$key] ?? null;
        if ($entry === null || $entry["expiresAt"] <= time()) {
            unset(self::$entries[$key]);
            return null;
        }
        return $entry["response"];
    }

    #[Override]
    public function store(string $key, IdempotentResponse $response, int $ttl): void
    {
        self::$entries[$key] = ["response" => $response, "expiresAt" => time() + $ttl];
    }

    public static function reset(): void
    {
        self::$entries = [];
    }
}
