<?php

namespace Sabatier\Service;

/**
 * Decorates a Response object by adding a Cache-Control header to enable
 * caching behavior for the response.
 *
 * This transformer modifies the response's header fields to include a
 * "Cache-Control" directive built from the supplied HTTPCachePolicy.
 */
class CacheHeaderTransformer extends ResponseTransformer
{
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        $policy = $context->cachePolicy ?? Application::shared()->cachePolicy;
        $headers = $response->allHeaderFields;
        $directives = [$policy->visibility, "max-age=$policy->maxAge"];
        if ($policy->staleWhileRevalidate !== null) {
            $directives[] = "stale-while-revalidate=$policy->staleWhileRevalidate";
        }
        $headers["Cache-Control"] = implode(", ", $directives);
        if ($policy->vary !== null) {
            $headers["Vary"] = $policy->vary;
        }
        parent::__construct($response, $context);
    }
}
