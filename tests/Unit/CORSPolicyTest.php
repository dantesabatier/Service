<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Set;
use Sabatier\Service\CORSPolicy;

final class CORSPolicyTest extends TestCase
{
    #[Test]
    public function wildcardAllowsAnyOrigin(): void
    {
        $policy = new CORSPolicy(allowedOrigins: new Set(["*"]));
        $this->assertTrue($policy->allowsOrigin("https://example.com"));
        $this->assertTrue($policy->allowsOrigin("https://anything.else"));
    }

    #[Test]
    public function exactOriginIsAllowed(): void
    {
        $policy = new CORSPolicy(allowedOrigins: new Set(["https://example.com"]));
        $this->assertTrue($policy->allowsOrigin("https://example.com"));
    }

    #[Test]
    public function unknownOriginIsRejected(): void
    {
        $policy = new CORSPolicy(allowedOrigins: new Set(["https://example.com"]));
        $this->assertFalse($policy->allowsOrigin("https://attacker.com"));
    }

    #[Test]
    public function multipleOriginsAllowEachIndividually(): void
    {
        $policy = new CORSPolicy(allowedOrigins: new Set(["https://a.com", "https://b.com"]));
        $this->assertTrue($policy->allowsOrigin("https://a.com"));
        $this->assertTrue($policy->allowsOrigin("https://b.com"));
        $this->assertFalse($policy->allowsOrigin("https://c.com"));
    }

    #[Test]
    public function emptyOriginsRejectsEverything(): void
    {
        $policy = new CORSPolicy();
        $this->assertFalse($policy->allowsOrigin("https://example.com"));
    }

    #[Test]
    public function wildcardDetectedViaAllowsOrigin(): void
    {
        $policy = new CORSPolicy(allowedOrigins: new Set(["*"]));
        $this->assertTrue($policy->allowsOrigin("*"));
    }

    #[Test]
    public function specificOriginPolicyDoesNotMatchWildcard(): void
    {
        $policy = new CORSPolicy(allowedOrigins: new Set(["https://example.com"]));
        $this->assertFalse($policy->allowsOrigin("*"));
    }

    #[Test]
    public function isEmptyWhenNothingConfigured(): void
    {
        $this->assertTrue(new CORSPolicy()->isEmpty);
    }

    #[Test]
    public function isNotEmptyWhenOriginsConfigured(): void
    {
        $this->assertFalse(new CORSPolicy(allowedOrigins: new Set(["https://example.com"]))->isEmpty);
    }

    #[Test]
    public function isNotEmptyWhenMethodsConfigured(): void
    {
        $this->assertFalse(new CORSPolicy(allowedMethods: new Set(["GET"]))->isEmpty);
    }

    #[Test]
    public function isNotEmptyWhenHeadersConfigured(): void
    {
        $this->assertFalse(new CORSPolicy(allowedHeaders: new Set(["Authorization"]))->isEmpty);
    }

    #[Test]
    public function isNotEmptyWhenCredentialsAllowed(): void
    {
        $this->assertFalse(new CORSPolicy(allowCredentials: true)->isEmpty);
    }

    #[Test]
    public function exposedHeadersStoredCorrectly(): void
    {
        $policy = new CORSPolicy(exposedHeaders: new Set(["X-Custom-Header"]));
        $this->assertTrue($policy->exposedHeaders->contains(fn(string $h) => $h === "X-Custom-Header"));
    }
}
