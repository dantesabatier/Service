<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Date;
use Sabatier\Service\JSONWebTokenPayload;
use const Sabatier\Service\JWTEnabledKey;
use const Sabatier\Service\JWTIssuerKey;
use const Sabatier\Service\JWTScopesKey;
use const Sabatier\Service\JWTSubjectKey;
use const Sabatier\Service\JWTVersionKey;

final class JSONWebTokenPayloadTest extends TestCase
{
    #[Test]
    public function defaultConstructorHasNullProperties(): void
    {
        $p = new JSONWebTokenPayload();
        $this->assertNull($p->issuer);
        $this->assertNull($p->subject);
        $this->assertNull($p->isEnabled);
        $this->assertNull($p->expiration);
        $this->assertNull($p->notBefore);
        $this->assertNull($p->version);
        $this->assertNull($p->technicalScopes);
        $this->assertNull($p->authorizationScopes);
    }

    #[Test]
    public function constructorSetsIssuer(): void
    {
        $p = new JSONWebTokenPayload(issuer: "my-service");
        $this->assertSame("my-service", $p->issuer);
    }

    #[Test]
    public function constructorSetsSubject(): void
    {
        $p = new JSONWebTokenPayload(subject: "user-123");
        $this->assertSame("user-123", $p->subject);
    }

    #[Test]
    public function constructorSetsIsEnabled(): void
    {
        $p = new JSONWebTokenPayload(isEnabled: true);
        $this->assertTrue($p->isEnabled);
    }

    #[Test]
    public function constructorSetsVersion(): void
    {
        $p = new JSONWebTokenPayload(version: 7);
        $this->assertSame(7, $p->version);
    }

    #[Test]
    public function constructorSetsTechnicalScopes(): void
    {
        $p = new JSONWebTokenPayload(technicalScopes: ["api:read", "api:write"]);
        $this->assertSame(["api:read", "api:write"], $p->technicalScopes);
    }

    #[Test]
    public function constructorSetsAuthorizationScopes(): void
    {
        $p = new JSONWebTokenPayload(authorizationScopes: ["admin", "user"]);
        $this->assertSame(["admin", "user"], $p->authorizationScopes);
    }

    #[Test]
    public function constructorSetsExpirationFromDate(): void
    {
        $date = new Date();
        $p = new JSONWebTokenPayload(expirationDate: $date);
        $this->assertEqualsWithDelta($date->timeIntervalSinceReferenceDate, $p->expiration, 1.0);
    }

    #[Test]
    public function factoryCreatesFromRawArray(): void
    {
        $p = JSONWebTokenPayload::payload([
            JWTIssuerKey => "issuer",
            JWTSubjectKey => "sub-1",
            JWTEnabledKey => true,
            JWTVersionKey => 2,
            JWTScopesKey => ["s1", "s2"],
        ]);
        $this->assertSame("issuer", $p->issuer);
        $this->assertSame("sub-1", $p->subject);
        $this->assertTrue($p->isEnabled);
        $this->assertSame(2, $p->version);
        $this->assertSame(["s1", "s2"], $p->technicalScopes);
    }

    #[Test]
    public function jsonSerializeReturnsRawValue(): void
    {
        $raw = [JWTIssuerKey => "iss", JWTSubjectKey => "sub"];
        $p = JSONWebTokenPayload::payload($raw);
        $this->assertSame($raw, $p->jsonSerialize());
    }
}
