<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ProcessInfo;

/**
 * An immutable value object that describes the HTTP caching behavior for a response.
 *
 * `HTTPCachePolicy` drives both `CacheHeaderTransformer` (Cache-Control header) and
 * `ConditionalGetTransformer` (ETag generation and 304 negotiation). It is the single
 * source of truth for all cache-related decisions in the response pipeline.
 *
 * ## Properties
 * - `maxAge`: Seconds the response may be cached. Defaults to 3600.
 * - `visibility`: `"public"` (shared caches) or `"private"` (client-only). Defaults to `"public"`.
 * - `staleWhileRevalidate`: If set, adds `stale-while-revalidate=N` to Cache-Control.
 * - `vary`: If set, adds a `Vary` header to enable content negotiation caching.
 * - `etagEnabled`: When `false`, `ConditionalGetTransformer` skips ETag generation entirely.
 *
 * ## Configuration
 * The `Application::$cachePolicy` property is the central configuration point.
 * The static `policy()` factory reads from environment variables, enabling
 * deployment-level tuning without code changes:
 *
 * - `HTTP_CACHE_MAX_AGE`
 * - `HTTP_CACHE_VISIBILITY`
 * - `HTTP_CACHE_STALE_WHILE_REVALIDATE`
 * - `HTTP_CACHE_VARY`
 * - `HTTP_CACHE_ETAG_ENABLED`
 *
 * Override `Application::$cachePolicy` in the application delegate to provide
 * a policy programmatically:
 *
 * <code>
 * Application::shared()->cachePolicy = new HTTPCachePolicy(maxAge: 300, visibility: 'private');
 * </code>
 */
final readonly class HTTPCachePolicy
{
    public function __construct(public int $maxAge = HTTPCacheMaxAgeDefault, public string $visibility = HTTPCacheVisibilityDefault, public ?int $staleWhileRevalidate = null, public ?string $vary = null, public bool $etagEnabled = HTTPCacheETagEnabledDefault)
    {
    }

    public static function policy(): HTTPCachePolicy
    {
        $environment = ProcessInfo::processInfo()->environment;
        return new HTTPCachePolicy((int)($environment[HTTPCacheMaxAgeKey] ?? HTTPCacheMaxAgeDefault), (string)($environment[HTTPCacheVisibilityKey] ?? HTTPCacheVisibilityDefault), $environment[HTTPCacheStaleWhileRevalidateKey], $environment[HTTPCacheVaryKey], filter_var($environment[HTTPCacheETagEnabledKey] ?? HTTPCacheETagEnabledDefault, FILTER_VALIDATE_BOOL));
    }
}
