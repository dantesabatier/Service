<?php

namespace Sabatier\Service;

use Override;

/**
 * An APCu-backed rate limit store shared across PHP workers on the same server.
 *
 * This is the default `RateLimitStore` implementation. It uses APCu shared memory
 * to maintain per-key counters that survive across requests and are visible to all
 * worker processes on the same machine.
 *
 * Requires the APCu PHP extension (`ext-apcu`). For multiserver deployments, replace
 * this with a distributed store (e.g., Redis) via `Application::$rateLimitStore`.
 *
 * @see RateLimitStore
 */
final class APCuRateLimitStore implements RateLimitStore
{
    #[Override]
    public function increment(string $key, int $windowSeconds): int
    {
        if (apcu_add($key, 1, $windowSeconds)) {
            return 1;
        }
        /** @var int $count */
        $count = apcu_inc($key);
        return $count;
    }

    #[Override]
    public function ttl(string $key): int
    {
        $info = apcu_key_info($key);
        if ($info === null) {
            return 0;
        }
        $remaining = (int)($info["creation_time"] + $info["ttl"] - time());
        return max(0, $remaining);
    }
}
