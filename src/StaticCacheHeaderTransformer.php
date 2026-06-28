<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * Decorates a Response object with a `Cache-Control` header derived from the
 * `StaticResourceDisposition` carried in the context.
 *
 * Static bundle resources (public directories such as Resources, vendor, and
 * node_modules) are served with `public, max-age=N`, optionally `immutable`,
 * independently of the application's HTTPCachePolicy — which targets dynamic
 * API responses and defaults to `private, max-age=0`. Because it writes the
 * header in the user pipeline, the downstream infrastructure CacheHeaderTransformer
 * leaves it untouched.
 *
 * The header is only written when the disposition is present and cacheable; otherwise
 * the response passes through unchanged.
 */
final class StaticCacheHeaderTransformer extends ResponseTransformer
{
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        $disposition = $context->staticResourceDisposition;
        if ($disposition === null || !$disposition->cacheable) {
            parent::__construct($response, $context);
            return;
        }
        $directives = ["public", "max-age=$disposition->maxAge"];
        if ($disposition->immutable) {
            $directives[] = "immutable";
        }
        $headers = $response->allHeaderFields;
        $headers["Cache-Control"] = implode(", ", $directives);
        parent::__construct($response, $context);
    }
}
