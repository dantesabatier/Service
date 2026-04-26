<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * Writes X-RateLimit-* informational headers onto the response.
 *
 * When rate limit state is available in the ResponseTransformerContext, adds three standard
 * headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, and `X-RateLimit-Reset` (Unix timestamp).
 * If no rate limit info is present — for example, when rate limiting is disabled — this transformer
 * is a no-op.
 *
 * @see RateLimitInfo
 * @see RateLimitPolicy
 */
final class RateLimitHeaderTransformer extends ResponseTransformer
{
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        $info = $context->rateLimitInfo;
        if ($info !== null) {
            $headers = $response->allHeaderFields;
            $headers["X-RateLimit-Limit"] = (string)$info->limit;
            $headers["X-RateLimit-Remaining"] = (string)$info->remaining;
            $headers["X-RateLimit-Reset"] = (string)$info->reset;
        }
        parent::__construct($response, $context);
    }
}
