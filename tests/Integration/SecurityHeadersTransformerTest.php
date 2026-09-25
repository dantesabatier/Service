<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\URL;
use Sabatier\Service\Response;
use Sabatier\Service\ResponseTransformerContext;
use Sabatier\Service\SecurityHeadersPolicy;
use Sabatier\Service\SecurityHeadersTransformer;

final class SecurityHeadersTransformerTest extends TestCase
{
    private function response(): Response
    {
        return new Response(new URL('http://localhost/'));
    }

    private function transform(Response $response, ?SecurityHeadersPolicy $policy): Response
    {
        return new SecurityHeadersTransformer($response, new ResponseTransformerContext(securityHeadersPolicy: $policy))->response;
    }

    // --- Sin política ---

    #[Test]
    public function noHeadersWhenNoPolicyInContext(): void
    {
        $response = $this->transform($this->response(), null);
        $headers = $response->allHeaderFields;
        $this->assertNull($headers['X-Content-Type-Options']);
        $this->assertNull($headers['X-Frame-Options']);
        $this->assertNull($headers['Content-Security-Policy']);
    }

    // --- Valores explícitos ---

    #[Test]
    public function allHeadersPresentWhenPolicyFullyConfigured(): void
    {
        $policy = new SecurityHeadersPolicy(
            contentSecurityPolicy: "default-src 'self'",
            strictTransportSecurity: 'max-age=31536000',
            xContentTypeOptions: 'nosniff',
            xFrameOptions: 'DENY',
            referrerPolicy: 'no-referrer',
            permissionsPolicy: 'geolocation=()',
        );
        $headers = $this->transform($this->response(), $policy)->allHeaderFields;
        $this->assertSame("default-src 'self'", $headers['Content-Security-Policy']);
        $this->assertSame('max-age=31536000', $headers['Strict-Transport-Security']);
        $this->assertSame('nosniff', $headers['X-Content-Type-Options']);
        $this->assertSame('DENY', $headers['X-Frame-Options']);
        $this->assertSame('no-referrer', $headers['Referrer-Policy']);
        $this->assertSame('geolocation=()', $headers['Permissions-Policy']);
    }

    // --- Valores nulos omiten el header ---

    #[Test]
    public function nullContentSecurityPolicyOmitsHeader(): void
    {
        $policy = new SecurityHeadersPolicy(contentSecurityPolicy: null);
        $this->assertNull($this->transform($this->response(), $policy)->allHeaderFields['Content-Security-Policy']);
    }

    #[Test]
    public function nullHSTSOmitsHeader(): void
    {
        $policy = new SecurityHeadersPolicy(strictTransportSecurity: null);
        $this->assertNull($this->transform($this->response(), $policy)->allHeaderFields['Strict-Transport-Security']);
    }

    // --- Defaults del framework ---

    #[Test]
    public function defaultXContentTypeOptionsIsNosniff(): void
    {
        $policy = new SecurityHeadersPolicy(xContentTypeOptions: 'nosniff');
        $this->assertSame('nosniff', $this->transform($this->response(), $policy)->allHeaderFields['X-Content-Type-Options']);
    }

    #[Test]
    public function defaultXFrameOptionsIsSameorigin(): void
    {
        $policy = new SecurityHeadersPolicy(xFrameOptions: 'SAMEORIGIN');
        $this->assertSame('SAMEORIGIN', $this->transform($this->response(), $policy)->allHeaderFields['X-Frame-Options']);
    }

    #[Test]
    public function defaultReferrerPolicyIsStrictOriginWhenCrossOrigin(): void
    {
        $policy = new SecurityHeadersPolicy(referrerPolicy: 'strict-origin-when-cross-origin');
        $this->assertSame('strict-origin-when-cross-origin', $this->transform($this->response(), $policy)->allHeaderFields['Referrer-Policy']);
    }

    #[Test]
    public function defaultPermissionsPolicyDisablesSensitiveAPIs(): void
    {
        $policy = new SecurityHeadersPolicy(permissionsPolicy: 'camera=(), microphone=(), geolocation=()');
        $value = $this->transform($this->response(), $policy)->allHeaderFields['Permissions-Policy'];
        $this->assertStringContainsString('camera=()', $value);
        $this->assertStringContainsString('microphone=()', $value);
        $this->assertStringContainsString('geolocation=()', $value);
    }

    // --- Idempotencia ---

    #[Test]
    public function existingHeadersAreOverwrittenByPolicy(): void
    {
        $response = $this->response();
        $response->allHeaderFields['X-Frame-Options'] = 'ALLOWALL';
        $policy = new SecurityHeadersPolicy(xFrameOptions: 'DENY');
        $result = $this->transform($response, $policy);
        $this->assertSame('DENY', $result->allHeaderFields['X-Frame-Options']);
    }
}
