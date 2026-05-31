<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\ProcessInfo;
use function Sabatier\Foundation\string_split_trimmed;

/**
 * An immutable value object that describes the rate limiting behavior for all incoming requests.
 *
 * `RateLimitPolicy` is the single configuration point for rate limiting. It controls whether
 * limiting is active, how many requests are allowed per window, and how long each window lasts.
 *
 * ## Default behavior
 * Out of the box, authenticated users are allowed 120 requests per 60-second window; unauthenticated
 * clients (keyed by IP) are allowed 30. Exceeding the limit produces a 429 Too Many Requests
 * response with a `Retry-After` header.
 *
 * ## Configuration
 * `Application::$rateLimitPolicy` is the central override point. The static `policy()` factory
 * reads from environment variables, enabling deployment-level tuning without code changes:
 *
 * - `RATE_LIMIT_ENABLED` (default: `true`)
 * - `RATE_LIMIT_MAX_REQUESTS_USER` (default: `120`)
 * - `RATE_LIMIT_MAX_REQUESTS_IP` (default: `30`)
 * - `RATE_LIMIT_WINDOW_SECONDS` (default: `60`)
 * - `RATE_LIMIT_TRUSTED_PROXIES` (default: `""`) — comma-separated list of trusted reverse proxy IPs or CIDRs (e.g. `10.0.0.1,192.168.1.0/24`). When set, the real client IP is resolved from `X-Forwarded-For` by walking the chain right-to-left and discarding known proxy addresses.
 *
 * Override in the application delegate for programmatic control:
 *
 * <code>
 * Application::shared()->rateLimitPolicy = new RateLimitPolicy(maxRequestsUser: 240, maxRequestsIP: 60);
 * </code>
 *
 * @see RateLimitStore
 * @see TooManyRequestsException
 * @see Application::$rateLimitPolicy
 */
final readonly class RateLimitPolicy
{
    /** @param string[] $trustedProxies */
    public function __construct(public bool $enabled = RateLimitEnabledDefault, public int $maxRequestsUser = RateLimitMaxRequestsUserDefault, public int $maxRequestsIP = RateLimitMaxRequestsIPDefault, public int $windowSeconds = RateLimitWindowSecondsDefault, public array $trustedProxies = [])
    {
    }

    public static function policy(): RateLimitPolicy
    {
        $environment = ProcessInfo::processInfo()->environment;
        $proxies = string_split_trimmed((string)$environment[RateLimitTrustedProxiesKey]);
        return new RateLimitPolicy(filter_var($environment[RateLimitEnabledKey] ?? RateLimitEnabledDefault, FILTER_VALIDATE_BOOL), (int)($environment[RateLimitMaxRequestsUserKey] ?? RateLimitMaxRequestsUserDefault), (int)($environment[RateLimitMaxRequestsIPKey] ?? RateLimitMaxRequestsIPDefault), (int)($environment[RateLimitWindowSecondsKey] ?? RateLimitWindowSecondsDefault), $proxies);
    }
}
