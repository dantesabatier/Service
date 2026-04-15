<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ProcessInfo;

/** @internal */
final readonly class SecurityHeadersPolicy
{
    public function __construct(public ?string $contentSecurityPolicy = null, public ?string $strictTransportSecurity = null, public string $xContentTypeOptions = SecurityXContentTypeOptionsDefault, public string $xFrameOptions = SecurityXFrameOptionsDefault, public string $referrerPolicy = SecurityReferrerPolicyDefault, public string $permissionsPolicy = SecurityPermissionsPolicyDefault)
    {
    }

    public static function policy(): SecurityHeadersPolicy
    {
        $environment = ProcessInfo::processInfo()->environment;
        return new SecurityHeadersPolicy($environment[SecurityContentSecurityPolicyKey], $environment[SecurityStrictTransportSecurityKey], [SecurityXContentTypeOptionsKey] ?? SecurityXContentTypeOptionsDefault, $environment[SecurityXFrameOptionsKey] ?? SecurityXFrameOptionsDefault, $environment[SecurityReferrerPolicyKey] ?? SecurityReferrerPolicyDefault, $environment[SecurityPermissionsPolicyKey] ?? SecurityPermissionsPolicyDefault);
    }
}
