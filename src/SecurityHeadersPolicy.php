<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\ProcessInfo;

/**
 * An immutable value object that describes the security headers applied to every response.
 *
 * `SecurityHeadersPolicy` drives `SecurityHeadersTransformer`, which writes standard
 * browser security directives on every outgoing response. All properties are nullable;
 * a null value omits the corresponding header, though several properties have non-null
 * defaults sourced from environment variables.
 *
 * ## Configuration
 * `Application::$securityHeadersPolicy` is the central override point. The static `policy()`
 * factory reads the following environment variables:
 *
 * - `SECURITY_CONTENT_SECURITY_POLICY`
 * - `SECURITY_STRICT_TRANSPORT_SECURITY`
 * - `SECURITY_X_CONTENT_TYPE_OPTIONS`
 * - `SECURITY_X_FRAME_OPTIONS`
 * - `SECURITY_REFERRER_POLICY`
 * - `SECURITY_PERMISSIONS_POLICY`
 *
 * Override in the application delegate for programmatic control:
 *
 * <code>
 * Application::shared()->securityHeadersPolicy = new SecurityHeadersPolicy(contentSecurityPolicy: "default-src 'self'");
 * </code>
 *
 * @see SecurityHeadersTransformer
 * @see Application::$securityHeadersPolicy
 */
final readonly class SecurityHeadersPolicy
{
    public function __construct(public ?string $contentSecurityPolicy = null, public ?string $strictTransportSecurity = null, public ?string $xContentTypeOptions = null, public ?string $xFrameOptions = null, public ?string $referrerPolicy = null, public ?string $permissionsPolicy = null)
    {
    }

    public static function policy(): SecurityHeadersPolicy
    {
        $environment = ProcessInfo::processInfo()->environment;
        return new SecurityHeadersPolicy($environment[SecurityContentSecurityPolicyKey], $environment[SecurityStrictTransportSecurityKey], $environment[SecurityXContentTypeOptionsKey] ?? SecurityXContentTypeOptionsDefault, $environment[SecurityXFrameOptionsKey] ?? SecurityXFrameOptionsDefault, $environment[SecurityReferrerPolicyKey] ?? SecurityReferrerPolicyDefault, $environment[SecurityPermissionsPolicyKey] ?? SecurityPermissionsPolicyDefault);
    }
}
