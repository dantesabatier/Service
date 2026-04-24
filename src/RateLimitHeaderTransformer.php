<?php

namespace Sabatier\Service;

/**
 * @internal
 */
final class RateLimitHeaderTransformer extends ResponseTransformer
{
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        $info = $context->rateLimitInfo;
        if ($info !== null) {
            $headers = $response->allHeaderFields;
            $headers["X-RateLimit-Limit"] = (string)$info->limit;
            $headers["X-RateLimit-Remaining"] = (string)$info->remaining;
            $headers["X-RateLimit-Reset"] = (string)$info->reset;
        }
        parent::__construct($response, $context);
    }
}
