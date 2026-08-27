<?php

declare(strict_types=1);

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
 * - `maxAge`: Seconds the response may be cached. Defaults to 0 — nothing is cached unless a
 *   responder or the environment asks for it.
 * - `visibility`: `"public"` (shared caches) or `"private"` (client-only). Defaults to `"private"`.
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
    /**
     * @param int $maxAge Seconds a response may be cached.
     * @param string $visibility Cache visibility for shared or client-only caches.
     * @param int|null $staleWhileRevalidate Seconds a stale response may be served while it is revalidated, or `null` to omit the directive.
     * @param string|null $vary The `Vary` header value, or `null` when responses do not vary by request headers.
     * @param bool $etagEnabled Whether conditional responses use entity tags.
     */
    public function __construct(public int $maxAge = HTTPCacheMaxAgeDefault, public string $visibility = HTTPCacheVisibilityDefault, public ?int $staleWhileRevalidate = null, public ?string $vary = null, public bool $etagEnabled = HTTPCacheETagEnabledDefault)
    {
    }

    public static function policy(): HTTPCachePolicy
    {
        $environment = ProcessInfo::processInfo()->environment;
        return new HTTPCachePolicy((int)($environment[HTTPCacheMaxAgeKey] ?? HTTPCacheMaxAgeDefault), (string)($environment[HTTPCacheVisibilityKey] ?? HTTPCacheVisibilityDefault), $environment->offsetExists(HTTPCacheStaleWhileRevalidateKey) ? (int)$environment[HTTPCacheStaleWhileRevalidateKey] : null, $environment[HTTPCacheVaryKey], filter_var($environment[HTTPCacheETagEnabledKey] ?? HTTPCacheETagEnabledDefault, FILTER_VALIDATE_BOOL));
    }
}
