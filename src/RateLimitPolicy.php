<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ProcessInfo;

/**
 * An immutable value object that describes the rate limiting behavior for all incoming requests.
 *
 * `RateLimitPolicy` is the single configuration point for rate limiting. It controls whether
 * limiting is active, how many requests are allowed per window, and how long each window lasts.
 *
 * ## Default behavior
 * Out of the box, 60 requests per IP per 60-second window are allowed. Exceeding the limit
 * produces a 429 Too Many Requests response with a `Retry-After` header.
 *
 * ## Configuration
 * `Application::$rateLimitPolicy` is the central override point. The static `policy()` factory
 * reads from environment variables, enabling deployment-level tuning without code changes:
 *
 * - `RATE_LIMIT_ENABLED` (default: `true`)
 * - `RATE_LIMIT_MAX_REQUESTS` (default: `60`)
 * - `RATE_LIMIT_WINDOW_SECONDS` (default: `60`)
 *
 * Override in the application delegate for programmatic control:
 *
 * <code>
 * Application::shared()->rateLimitPolicy = new RateLimitPolicy(maxRequests: 120, windowSeconds: 60);
 * </code>
 *
 * @see RateLimitStore
 * @see TooManyRequestsException
 * @see Application::$rateLimitPolicy
 */
final readonly class RateLimitPolicy
{
    public function __construct(public bool $enabled = RateLimitEnabledDefault, public int $maxRequests = RateLimitMaxRequestsDefault, public int $windowSeconds = RateLimitWindowSecondsDefault)
    {
    }

    public static function policy(): RateLimitPolicy
    {
        $environment = ProcessInfo::processInfo()->environment;
        return new RateLimitPolicy(filter_var($environment[RateLimitEnabledKey] ?? RateLimitEnabledDefault, FILTER_VALIDATE_BOOL), (int)($environment[RateLimitMaxRequestsKey] ?? RateLimitMaxRequestsDefault), (int)($environment[RateLimitWindowSecondsKey] ?? RateLimitWindowSecondsDefault));
    }
}
