<?php

declare(strict_types=1);

namespace Sabatier\Service;

/** @var string Environment variable key for the `max-age` Cache-Control directive value in seconds. */
const HTTPCacheMaxAgeKey = "HTTP_CACHE_MAX_AGE";
/** @var string Environment variable key for the cache visibility directive (`public` or `private`). */
const HTTPCacheVisibilityKey = "HTTP_CACHE_VISIBILITY";
/** @var string Environment variable key for the `stale-while-revalidate` Cache-Control directive value in seconds. */
const HTTPCacheStaleWhileRevalidateKey = "HTTP_CACHE_STALE_WHILE_REVALIDATE";
/** @var string Environment variable key for the `Vary` response header value. */
const HTTPCacheVaryKey = "HTTP_CACHE_VARY";
/** @var string Environment variable key to enable or disable ETag generation (`true` or `false`). */
const HTTPCacheETagEnabledKey = "HTTP_CACHE_ETAG_ENABLED";
/** @var int Default `max-age` value in seconds. Zero means the response must be revalidated on every request. */
const HTTPCacheMaxAgeDefault = 0;
/** @var string Default cache visibility directive. */
const HTTPCacheVisibilityDefault = "private";
/** @var bool Default ETag generation state. */
const HTTPCacheETagEnabledDefault = true;
/** @var string Environment variable key overriding the `max-age` applied to fingerprinted static bundle resources (public directories such as Resources, vendor, and node_modules). */
const StaticResourceMaxAgeKey = "STATIC_RESOURCE_MAX_AGE";
/** @var int Default `max-age` in seconds for static bundle resources. One year, the conventional value for fingerprinted assets served with `immutable`. */
const StaticResourceMaxAgeDefault = 31536000;
/** @var int Default `max-age` in seconds for optional browser resources (`favicon.ico`, `robots.txt`, and similar). One day, since these are served from stable URLs without fingerprinting. */
const StaticResourceOptionalMaxAgeDefault = 86400;
/** @var string Environment variable key listing extra public directories (comma-separated, relative to the bundle root) whose contents are served as cacheable static resources — e.g. a bundler output directory like `Build` or `dist`. */
const StaticPublicDirectoriesKey = "STATIC_PUBLIC_DIRECTORIES";
