<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * Describes how a static resource should be handled.
 */
final readonly class StaticResourceDisposition
{
    /**
     * @param bool $shouldHandle Whether the resource should be handled by the static resource responder.
     * @param bool $isOptional Whether the resource is considered optional.
     * @param bool $allowEmptyResponse Whether an empty response is allowed when the resource is missing.
     * @param bool $cacheable Whether the response may be cached.
     * @param bool $isProtectedContentAvailable Whether the requested resource is permitted to be served even if it resides outside standard public directories.
     * @param int $maxAge The `max-age` in seconds applied to the `Cache-Control` directive when the resource is cacheable. Ignored when `$cacheable` is `false`.
     * @param bool $immutable Whether the `immutable` directive is appended to `Cache-Control`. Reserved for fingerprinted resources whose contents never change under a stable URL. Ignored when `$cacheable` is `false`.
     */
    public function __construct(public bool $shouldHandle, public bool $isOptional, public bool $allowEmptyResponse, public bool $cacheable, public bool $isProtectedContentAvailable, public int $maxAge = 0, public bool $immutable = false)
    {
    }
}
