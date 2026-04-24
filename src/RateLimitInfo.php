<?php

namespace Sabatier\Service;

/**
 * An immutable snapshot of the rate limit state for the current request.
 *
 * Produced by `Application::enforceRateLimitIfNeeded()` and propagated through
 * `ResponseTransformerContext` to `RateLimitHeaderTransformer`, which writes the
 * standard `X-RateLimit-*` headers onto every response.
 *
 * @see RateLimitPolicy
 * @see RateLimitHeaderTransformer
 */
final readonly class RateLimitInfo
{
    /**
     * @param int $limit     The maximum number of requests allowed in the window.
     * @param int $remaining The number of requests still available in the current window.
     * @param int $reset     Unix timestamp at which the current window expires and the counter resets.
     */
    public function __construct(
        public int $limit,
        public int $remaining,
        public int $reset,
    ) {}
}
