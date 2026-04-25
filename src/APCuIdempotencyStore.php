<?php

namespace Sabatier\Service;

use Override;

/**
 * An APCu-backed idempotency store shared across PHP workers on the same server.
 *
 * This is the default `IdempotencyStore` implementation. It uses APCu shared memory
 * to persist response snapshots across requests, visible to all worker processes on
 * the same machine.
 *
 * Requires the APCu PHP extension (`ext-apcu`). For multiserver deployments, replace
 * this with a distributed store via `Application::$idempotencyStore`.
 *
 * @see IdempotencyStore
 */
final class APCuIdempotencyStore implements IdempotencyStore
{
    #[Override]
    public function get(string $key): ?IdempotentResponse
    {
        $value = apcu_fetch($key, $success);
        if (!$success) {
            return null;
        }
        /** @var IdempotentResponse $result */
        $result = unserialize((string)$value);
        return $result;
    }

    #[Override]
    public function store(string $key, IdempotentResponse $response, int $ttl): void
    {
        apcu_store($key, serialize($response), $ttl);
    }
}
