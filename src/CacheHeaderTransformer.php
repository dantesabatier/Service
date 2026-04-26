<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * Decorates a Response object by adding a Cache-Control header derived from the supplied HTTPCachePolicy.
 *
 * When used as part of the infrastructure transformer layer, this transformer acts as a
 * default: it writes Cache-Control only if the response does not already carry one. This
 * allows per-endpoint or per-action transformers (e.g. PrivateCacheHeaderTransformer,
 * NoCacheHeaderTransformer) that run in the user pipeline to take precedence, while still
 * guaranteeing that every response emits an explicit Cache-Control header.
 */
class CacheHeaderTransformer extends ResponseTransformer
{
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        $policy = $context->cachePolicy;
        if ($policy === null) {
            parent::__construct($response, $context);
            return;
        }
        $headers = $response->allHeaderFields;
        if (!$headers["Cache-Control"]) {
            $directives = [$policy->visibility, "max-age=$policy->maxAge"];
            if ($policy->staleWhileRevalidate !== null) {
                $directives[] = "stale-while-revalidate=$policy->staleWhileRevalidate";
            }
            $headers["Cache-Control"] = implode(", ", $directives);
            if ($policy->vary !== null) {
                $headers["Vary"] = $policy->vary;
            }
        }
        parent::__construct($response, $context);
    }
}
