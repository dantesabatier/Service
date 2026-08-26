<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Service\JSONWebToken;
use Sabatier\Service\JSONWebTokenCoderStrategyRegistrar;
use Sabatier\Service\JSONWebTokenException;
use Sabatier\Service\JSONWebTokenHeader;
use Sabatier\Service\JSONWebTokenPayload;
use Sabatier\Service\JSONWebTokenRS256DecoderStrategy;
use Sabatier\Service\JSONWebTokenRS256EncoderStrategy;
use Sabatier\Service\JSONWebTokenService;
use Sabatier\Service\JSONWebTokenSigningAlgorithm;
use Sabatier\Foundation\ProcessInfo;
use const Sabatier\Service\JWTIssuerEnvironmentKey;
use const Sabatier\Service\JWTIssuerKey;
use const Sabatier\Service\JWTSubjectKey;

final class JSONWebTokenServiceTest extends TestCase
{
    private const KEY = 'test-hs256-secret';

    private ?string $originalIssuer = null;

    #[Override]
    protected function setUp(): void
    {
        $env = ProcessInfo::processInfo()->environment;
        $this->originalIssuer = $env[JWTIssuerEnvironmentKey];
        unset($env[JWTIssuerEnvironmentKey]);
    }

    #[Override]
    protected function tearDown(): void
    {
        $env = ProcessInfo::processInfo()->environment;
        if ($this->originalIssuer !== null) {
            $env[JWTIssuerEnvironmentKey] = $this->originalIssuer;
        } else {
            unset($env[JWTIssuerEnvironmentKey]);
        }
    }

    private const RSA_PRIVATE_KEY = <<<PEM
    -----BEGIN RSA PRIVATE KEY-----
    MIICXQIBAAKBgQCuho711xur6hCtUmkfII3XY293L3sKAbd7Fo5WaheRwHaT6s0W
    1AunAAI8yCiF9gZuoqh6BbXiIosoB7EverVJwHinirQ3pkfA+pvw6g/06e0yjNpq
    nVHGNZngZunjF6qLpAZ6kzLuVN5IQzrmTkdFrMWy+DnPPDM1repZ5Eh+UQIDAQAB
    AoGAAKOdgmj3QPnqdbgHioWj/1Xt4pHZ8X9wHJNIkihxTadWx9PkTGEaadImL/LL
    szHjdCREWa4LrHhT6iGdFH9uioUNUt01cg3COA201mmbT9szUxTpt7FrUCrh2c0S
    enDwlFjk/cqvZOWF/7FRFhW5adpdiLQJyG/nEhAt/UZCfAECQQDbYVnfJRUAxu4l
    Rq0ciGXIr+Jl52kR7I4Ow8Ln2/UjAGhpYlDT/Ekc6+Z1/StQ8xNpwxkWr59B6CMl
    lQGDaqkpAkEAy6h1orIOKXqftoQSQyghyINpf7JcELjcNcANlBb7YGA7Qb8qF4xs
    xezRdxOzuZjp9t0xzdI2to09/751+rlI6QJBAL6GhLvUg7IiEm9TO0L9fpBVmGTy
    HgFQFWvjPiGJmRMl5ognt5TzlTfF9GfiUL1D7kc7Bk36hnCBwAyCpUbR2kkCQBAQ
    4AbPqRJYnBTX4mDt34xj4YSzW1PuYWDUH74Y+gemT8ZmADoPV91dS0DrivgPOhXB
    aVZlSO+pwMRWEBSRXVECQQCiQWCTLWWDEl3wGaNNQ68YLRqnZ8ysHx3jF5MudO+Q
    Ro2p+a0l4XY7mW+/KXu4KagewqWsbJIEENdbq+qBI4mq
    -----END RSA PRIVATE KEY-----
    PEM;

    private const RSA_PUBLIC_KEY = <<<PEM
    -----BEGIN PUBLIC KEY-----
    MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCuho711xur6hCtUmkfII3XY293
    L3sKAbd7Fo5WaheRwHaT6s0W1AunAAI8yCiF9gZuoqh6BbXiIosoB7EverVJwHin
    irQ3pkfA+pvw6g/06e0yjNpqnVHGNZngZunjF6qLpAZ6kzLuVN5IQzrmTkdFrMWy
    +DnPPDM1repZ5Eh+UQIDAQAB
    -----END PUBLIC KEY-----
    PEM;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        JSONWebTokenCoderStrategyRegistrar::register();
    }

    private function hs256(?string $issuer = null): JSONWebTokenService
    {
        return new JSONWebTokenService(self::KEY, $issuer);
    }

    // --- Encode format ---

    #[Test]
    public function encodeProducesThreeComponentToken(): void
    {
        $this->assertCount(3, explode('.', $this->hs256()->encode([JWTSubjectKey => 'u'])));
    }

    // --- HS256 round-trip ---

    #[Test]
    public function decodeAfterEncodePreservesSubject(): void
    {
        $svc = $this->hs256();
        $decoded = $svc->decode($svc->encode([JWTSubjectKey => 'user-42']));
        $this->assertSame('user-42', $decoded->payload->subject);
    }

    #[Test]
    public function decodedTokenHasHs256AlgorithmHeader(): void
    {
        $svc = $this->hs256();
        $decoded = $svc->decode($svc->encode([JWTSubjectKey => 'u']));
        $this->assertSame(JSONWebTokenSigningAlgorithm::hs256->value, $decoded->header->alg);
    }

    #[Test]
    public function decodeAfterEncodePreservesAllClaims(): void
    {
        $svc = $this->hs256();
        $decoded = $svc->decode($svc->encode([JWTSubjectKey => 'u', JWTIssuerKey => 'svc']));
        $this->assertSame('u', $decoded->payload->subject);
        $this->assertSame('svc', $decoded->payload->issuer);
    }

    // --- Tamper detection ---

    #[Test]
    public function tamperedSignatureThrowsException(): void
    {
        $parts = explode('.', $this->hs256()->encode([JWTSubjectKey => 'u']));
        $parts[2] = 'invalidsig';
        $this->expectException(JSONWebTokenException::class);
        $this->hs256()->decode(implode('.', $parts));
    }

    #[Test]
    public function tamperedPayloadThrowsException(): void
    {
        $svc = $this->hs256();
        $parts = explode('.', $svc->encode([JWTSubjectKey => 'u']));
        $parts[1] = rtrim(strtr(base64_encode(json_encode(['sub' => 'hacker'])), '+/', '-_'), '=');
        $this->expectException(JSONWebTokenException::class);
        $svc->decode(implode('.', $parts));
    }

    #[Test]
    public function malformedTokenThrowsException(): void
    {
        $this->expectException(JSONWebTokenException::class);
        $this->hs256()->decode('not.a.valid.jwt.with.too.many.parts');
    }

    #[Test]
    public function wrongKeyThrowsException(): void
    {
        $encoded = $this->hs256()->encode([JWTSubjectKey => 'u']);
        $this->expectException(JSONWebTokenException::class);
        (new JSONWebTokenService('wrong-key'))->decode($encoded);
    }

    // --- Issuer validation ---

    #[Test]
    public function issuerMismatchThrowsException(): void
    {
        $svc = $this->hs256('expected');
        $token = $svc->encode([JWTSubjectKey => 'u', JWTIssuerKey => 'wrong']);
        $this->expectException(JSONWebTokenException::class);
        $svc->decode($token);
    }

    #[Test]
    public function correctIssuerPasses(): void
    {
        $svc = $this->hs256('my-svc');
        $decoded = $svc->decode($svc->encode([JWTSubjectKey => 'u', JWTIssuerKey => 'my-svc']));
        $this->assertSame('my-svc', $decoded->payload->issuer);
    }

    #[Test]
    public function missingIssuerInPayloadPassesAlways(): void
    {
        $svc = $this->hs256('my-svc');
        $decoded = $svc->decode($svc->encode([JWTSubjectKey => 'u']));
        $this->assertNull($decoded->payload->issuer);
    }

    // --- Issuer resolved from environment (JWT_ISSUER) ---

    #[Test]
    public function environmentIssuerIsUsedWhenNoExplicitIssuer(): void
    {
        ProcessInfo::processInfo()->environment[JWTIssuerEnvironmentKey] = "env-issuer";
        $svc = $this->hs256();
        $decoded = $svc->decode($svc->encode([JWTSubjectKey => "u", JWTIssuerKey => "env-issuer"]));
        $this->assertSame("env-issuer", $decoded->payload->issuer);
    }

    #[Test]
    public function environmentIssuerMismatchThrowsException(): void
    {
        ProcessInfo::processInfo()->environment[JWTIssuerEnvironmentKey] = "env-issuer";
        $svc = $this->hs256();
        $token = $svc->encode([JWTSubjectKey => "u", JWTIssuerKey => "wrong"]);
        $this->expectException(JSONWebTokenException::class);
        $svc->decode($token);
    }

    #[Test]
    public function explicitIssuerTakesPrecedenceOverEnvironment(): void
    {
        ProcessInfo::processInfo()->environment[JWTIssuerEnvironmentKey] = "env-issuer";
        $svc = $this->hs256("explicit-issuer");
        $token = $svc->encode([JWTSubjectKey => "u", JWTIssuerKey => "env-issuer"]);
        $this->expectException(JSONWebTokenException::class);
        $svc->decode($token);
    }

    #[Test]
    public function withoutEnvironmentIssuerValidationIsSkipped(): void
    {
        $svc = $this->hs256();
        $decoded = $svc->decode($svc->encode([JWTSubjectKey => "u", JWTIssuerKey => "any-issuer"]));
        $this->assertSame("any-issuer", $decoded->payload->issuer);
    }

    // --- RS256 ---

    #[Test]
    public function rs256RoundTripPreservesPayload(): void
    {
        $encoder = new JSONWebTokenRS256EncoderStrategy(self::RSA_PRIVATE_KEY);
        $decoder = new JSONWebTokenRS256DecoderStrategy(self::RSA_PUBLIC_KEY);
        $token = new JSONWebToken(
            new JSONWebTokenHeader(JSONWebTokenSigningAlgorithm::rs256->value),
            JSONWebTokenPayload::payload([JWTSubjectKey => 'rs256-user'])
        );
        $decoded = $decoder->decode($encoder->encode($token));
        $this->assertSame('rs256-user', $decoded->payload->subject);
    }

    #[Test]
    public function rs256TamperedSignatureThrowsException(): void
    {
        $encoder = new JSONWebTokenRS256EncoderStrategy(self::RSA_PRIVATE_KEY);
        $decoder = new JSONWebTokenRS256DecoderStrategy(self::RSA_PUBLIC_KEY);
        $token = new JSONWebToken(
            new JSONWebTokenHeader(JSONWebTokenSigningAlgorithm::rs256->value),
            JSONWebTokenPayload::payload([JWTSubjectKey => 'u'])
        );
        $parts = explode('.', $encoder->encode($token));
        $parts[2] = 'tampered';
        $this->expectException(JSONWebTokenException::class);
        $decoder->decode(implode('.', $parts));
    }
}
