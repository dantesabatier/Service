<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ProcessInfo;
use const Sabatier\Service\CORSAllowCredentialsKey;
use const Sabatier\Service\CORSAllowedHeadersKey;
use const Sabatier\Service\CORSAllowedMethodsKey;
use const Sabatier\Service\CORSAllowedOriginsKey;
use const Sabatier\Service\CORSExposedHeadersKey;
use Sabatier\Service\CORSPolicy;

final class CORSPolicyFromEnvTest extends TestCase
{
    private array $originalValues = [];

    #[Override]
    protected function setUp(): void
    {
        $env = ProcessInfo::processInfo()->environment;
        foreach ([CORSAllowedOriginsKey, CORSAllowedMethodsKey, CORSAllowedHeadersKey, CORSAllowCredentialsKey, CORSExposedHeadersKey] as $key) {
            $this->originalValues[$key] = $env[$key];
            unset($env[$key]);
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        $env = ProcessInfo::processInfo()->environment;
        foreach ($this->originalValues as $key => $value) {
            if ($value !== null) {
                $env[$key] = $value;
            } else {
                unset($env[$key]);
            }
        }
    }

    private function setEnv(string $origins = "", string $methods = "", string $headers = "", string $credentials = "false", string $exposedHeaders = ""): void
    {
        $env = ProcessInfo::processInfo()->environment;
        $env[CORSAllowedOriginsKey] = $origins;
        $env[CORSAllowedMethodsKey] = $methods;
        $env[CORSAllowedHeadersKey] = $headers;
        $env[CORSAllowCredentialsKey] = $credentials;
        $env[CORSExposedHeadersKey] = $exposedHeaders;
    }

    #[Test]
    public function parsesOriginsFromCommaSeparatedString(): void
    {
        $this->setEnv(origins: "https://a.com,https://b.com");
        $policy = CORSPolicy::policy();
        $this->assertTrue($policy->allowsOrigin("https://a.com"));
        $this->assertTrue($policy->allowsOrigin("https://b.com"));
        $this->assertFalse($policy->allowsOrigin("https://c.com"));
    }

    #[Test]
    public function trimsWhitespaceAroundOrigins(): void
    {
        $this->setEnv(origins: "https://a.com , https://b.com");
        $policy = CORSPolicy::policy();
        $this->assertTrue($policy->allowsOrigin("https://a.com"));
        $this->assertTrue($policy->allowsOrigin("https://b.com"));
    }

    #[Test]
    public function parsesWildcardOrigin(): void
    {
        $this->setEnv(origins: "*");
        $policy = CORSPolicy::policy();
        $this->assertTrue($policy->allowsOrigin("https://anything.com"));
    }

    #[Test]
    public function emptyOriginsProducesEmptySet(): void
    {
        /** @noinspection PhpRedundantOptionalArgumentInspection */
        $this->setEnv(origins: "");
        $policy = CORSPolicy::policy();
        $this->assertFalse($policy->allowsOrigin("https://example.com"));
    }

    #[Test]
    public function parsesMethods(): void
    {
        $this->setEnv(methods: "GET,POST,DELETE");
        $policy = CORSPolicy::policy();
        $this->assertFalse($policy->allowedMethods->isEmpty);
        $this->assertTrue($policy->allowedMethods->contains(fn(string $m) => $m === "GET"));
        $this->assertTrue($policy->allowedMethods->contains(fn(string $m) => $m === "POST"));
        $this->assertTrue($policy->allowedMethods->contains(fn(string $m) => $m === "DELETE"));
    }

    #[Test]
    public function parsesAllowedHeaders(): void
    {
        $this->setEnv(headers: "Authorization,Content-Type");
        $policy = CORSPolicy::policy();
        $this->assertTrue($policy->allowedHeaders->contains(fn(string $h) => $h === "Authorization"));
        $this->assertTrue($policy->allowedHeaders->contains(fn(string $h) => $h === "Content-Type"));
    }

    #[Test]
    public function parsesCredentialsTrueFromString(): void
    {
        $this->setEnv(credentials: "true");
        $this->assertTrue(CORSPolicy::policy()->allowCredentials);
    }

    #[Test]
    public function parsesCredentialsFalseFromString(): void
    {
        /** @noinspection PhpRedundantOptionalArgumentInspection */
        $this->setEnv(credentials: "false");
        $this->assertFalse(CORSPolicy::policy()->allowCredentials);
    }

    #[Test]
    public function parsesCredentialsTrueFromOne(): void
    {
        $this->setEnv(credentials: "1");
        $this->assertTrue(CORSPolicy::policy()->allowCredentials);
    }

    #[Test]
    public function parsesExposedHeaders(): void
    {
        $this->setEnv(exposedHeaders: "X-Request-Id,X-Trace");
        $policy = CORSPolicy::policy();
        $this->assertTrue($policy->exposedHeaders->contains(fn(string $h) => $h === "X-Request-Id"));
        $this->assertTrue($policy->exposedHeaders->contains(fn(string $h) => $h === "X-Trace"));
    }

    #[Test]
    public function emptyExposedHeadersProducesEmptySet(): void
    {
        /** @noinspection PhpRedundantOptionalArgumentInspection */
        $this->setEnv(exposedHeaders: "");
        $this->assertTrue(CORSPolicy::policy()->exposedHeaders->isEmpty);
    }
}
