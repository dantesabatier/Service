<?php

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
     * @param bool $isProtectedContentAvailable 
     */
    public function __construct(public bool $shouldHandle, public bool $isOptional, public bool $allowEmptyResponse, public bool $cacheable, public bool $isProtectedContentAvailable)
    {
    }
}
