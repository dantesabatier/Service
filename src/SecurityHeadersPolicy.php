<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ProcessInfo;

/** @internal */
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
