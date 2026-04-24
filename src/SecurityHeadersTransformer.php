<?php

namespace Sabatier\Service;

/** @internal */
final class SecurityHeadersTransformer extends ResponseTransformer
{
    public function __construct(Response $response, ResponseTransformerContext $context = new ResponseTransformerContext())
    {
        $policy = $context->securityHeadersPolicy ?? Application::shared()->securityHeadersPolicy;
        $headers = $response->allHeaderFields;
        $headers["X-Content-Type-Options"] = $policy->xContentTypeOptions;
        $headers["X-Frame-Options"] = $policy->xFrameOptions;
        $headers["Referrer-Policy"] = $policy->referrerPolicy;
        $headers["Permissions-Policy"] = $policy->permissionsPolicy;
        $headers["Strict-Transport-Security"] = $policy->strictTransportSecurity;
        $headers["Content-Security-Policy"] = $policy->contentSecurityPolicy;
        parent::__construct($response, $context);
    }
}
