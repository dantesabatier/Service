<?php

declare(strict_types=1);

namespace Sabatier\Service;

/**
 * A value object that carries optional contextual dependencies for response transformers.
 *
 * ResponseTransformerContext is the second parameter of every ResponseTransformer constructor,
 * allowing all transformers — both pipeline and manually-wired — to share a single, consistent
 * constructor contract: __construct(Response $response, ResponseTransformerContext $context).
 *
 * The pipeline always instantiates transformers with the default empty context.
 * Transformers that are wired manually (e.g. ConditionalGetTransformer, CORSResponseTransformer)
 * receive a populated context with the dependencies they need.
 *
 * Adding context to a new transformer type only requires adding a nullable property here;
 * no existing constructor signatures need to change.
 */
final readonly class ResponseTransformerContext
{
    public function __construct(public ?Request $request = null, public ?HTTPCachePolicy $cachePolicy = null, public ?CORSPolicy $corsPolicy = null, public ?SecurityHeadersPolicy $securityHeadersPolicy = null, public ?RateLimitInfo $rateLimitInfo = null)
    {
    }
}
