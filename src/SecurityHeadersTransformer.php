<?php

namespace Sabatier\Service;

/**
 * Applies security-related HTTP response headers derived from the SecurityHeadersPolicy in the ResponseTransformerContext.
 *
 * Writes the standard browser security headers on every response: `X-Content-Type-Options`,
 * `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Strict-Transport-Security`,
 * and `Content-Security-Policy`. Header values are sourced from the policy, which is configured
 * either programmatically via `Application::$securityHeadersPolicy` or through environment variables.
 *
 * @see SecurityHeadersPolicy
 * @see Application::$securityHeadersPolicy
 */
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
