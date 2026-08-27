<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\ProcessInfo;

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
 *
 * Every counter is keyed on `REMOTE_ADDR`, the real TCP peer, which no request header can forge.
 * The limiter runs before any credential is verified, so the username it sees is the one the
 * request *claims*: `rate_limit:ip:<address>` is charged for every request, and a request naming
 * a subject is charged to `rate_limit:ip:<address>:user:<claimed>` as well, at the larger
 * `maxRequestsUser` allowance on both. Clients sharing an egress address (behind a NAT or forward
 * proxy) therefore share the address counter whether or not they authenticate.
 *
 * @see Application::enforceRateLimitIfNeeded() for why the claim cannot be the whole key.
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
    /**
     * @param bool $enabled Whether rate limiting is active.
     * @param int $maxRequestsUser Requests allowed per window when the request names a subject.
     * @param int $maxRequestsIP Requests allowed per window for an anonymous address.
     * @param int $windowSeconds Length of the rate-limit window in seconds.
     */
    public function __construct(public bool $enabled = RateLimitEnabledDefault, public int $maxRequestsUser = RateLimitMaxRequestsUserDefault, public int $maxRequestsIP = RateLimitMaxRequestsIPDefault, public int $windowSeconds = RateLimitWindowSecondsDefault)
    {
    }

    public static function policy(): RateLimitPolicy
    {
        $environment = ProcessInfo::processInfo()->environment;
        return new RateLimitPolicy(filter_var($environment[RateLimitEnabledKey] ?? RateLimitEnabledDefault, FILTER_VALIDATE_BOOL), (int)($environment[RateLimitMaxRequestsUserKey] ?? RateLimitMaxRequestsUserDefault), (int)($environment[RateLimitMaxRequestsIPKey] ?? RateLimitMaxRequestsIPDefault), (int)($environment[RateLimitWindowSecondsKey] ?? RateLimitWindowSecondsDefault));
    }
}
