<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;

/**
 * A per-process, in-memory ETag store intended for testing and development.
 *
 * ETag strings are stored in a static array shared within a single PHP process
 * but not across workers or requests in standard FPM deployments. This makes it
 * unsuitable for production use.
 *
 * Use this store in unit tests where isolation and predictable behavior are needed.
 *
 * @see ETagStore
 * @see APCuETagStore
 */
final class InMemoryETagStore implements ETagStore
{
    /** @var array<string, array{etag: string, expiresAt: int}> */
    private static array $entries = [];

    #[Override]
    public function get(string $key): ?string
    {
        $entry = self::$entries[$key] ?? null;
        if ($entry === null || $entry["expiresAt"] <= time()) {
            unset(self::$entries[$key]);
            return null;
        }
        return $entry["etag"];
    }

    #[Override]
    public function set(string $key, string $etag, int $ttl): void
    {
        self::$entries[$key] = ["etag" => $etag, "expiresAt" => time() + $ttl];
    }

    #[Override]
    public function deleteWithPrefix(string $prefix): void
    {
        foreach (array_keys(self::$entries) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset(self::$entries[$key]);
            }
        }
    }

    public static function reset(): void
    {
        self::$entries = [];
    }
}
