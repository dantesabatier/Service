<?php

declare(strict_types=1);

namespace Sabatier\Service;

use APCUIterator;
use Override;

/**
 * An APCu-backed ETag store shared across PHP workers on the same server.
 *
 * This is the default `ETagStore` implementation. It uses APCu shared memory
 * to persist ETag strings across requests, visible to all worker processes on
 * the same machine.
 *
 * Requires the APCu PHP extension (`ext-apcu`). For multiserver deployments, replace
 * this with a distributed store via `Application::$etagStore`.
 *
 * @see ETagStore
 */
final class APCuETagStore implements ETagStore
{
    #[Override]
    public function get(string $key): ?string
    {
        $value = apcu_fetch($key, $success);
        return $success ? (string)$value : null;
    }

    #[Override]
    public function set(string $key, string $etag, int $ttl): void
    {
        apcu_store($key, $etag, $ttl);
    }

    #[Override]
    public function deleteWithPrefix(string $prefix): void
    {
        $iterator = new APCUIterator("/^" . preg_quote($prefix, "/") . "/");
        apcu_delete($iterator);
    }
}
