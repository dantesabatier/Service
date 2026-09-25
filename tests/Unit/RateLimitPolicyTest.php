<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Service\RateLimitPolicy;
use const Sabatier\Service\RateLimitEnabledDefault;
use const Sabatier\Service\RateLimitEnabledKey;
use const Sabatier\Service\RateLimitMaxRequestsIPDefault;
use const Sabatier\Service\RateLimitMaxRequestsIPKey;
use const Sabatier\Service\RateLimitMaxRequestsUserDefault;
use const Sabatier\Service\RateLimitMaxRequestsUserKey;
use const Sabatier\Service\RateLimitWindowSecondsDefault;
use const Sabatier\Service\RateLimitWindowSecondsKey;

final class RateLimitPolicyTest extends TestCase
{
    private array $originalValues = [];

    #[Override]
    protected function setUp(): void
    {
        $env = ProcessInfo::processInfo()->environment;
        foreach ([RateLimitEnabledKey, RateLimitMaxRequestsUserKey, RateLimitMaxRequestsIPKey, RateLimitWindowSecondsKey] as $key) {
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

    // --- Constructor defaults ---

    #[Test]
    public function enabledDefaultsToTrue(): void
    {
        $this->assertTrue(new RateLimitPolicy()->enabled);
    }

    #[Test]
    public function maxRequestsUserDefaultIs120(): void
    {
        $this->assertSame(RateLimitMaxRequestsUserDefault, new RateLimitPolicy()->maxRequestsUser);
        $this->assertSame(120, new RateLimitPolicy()->maxRequestsUser);
    }

    #[Test]
    public function maxRequestsIPDefaultIs30(): void
    {
        $this->assertSame(RateLimitMaxRequestsIPDefault, new RateLimitPolicy()->maxRequestsIP);
        $this->assertSame(30, new RateLimitPolicy()->maxRequestsIP);
    }

    #[Test]
    public function windowSecondsDefaultIs60(): void
    {
        $this->assertSame(RateLimitWindowSecondsDefault, new RateLimitPolicy()->windowSeconds);
        $this->assertSame(60, new RateLimitPolicy()->windowSeconds);
    }

    #[Test]
    public function constructorSetsExplicitValues(): void
    {
        $policy = new RateLimitPolicy(enabled: false, maxRequestsUser: 300, maxRequestsIP: 75, windowSeconds: 120);
        $this->assertFalse($policy->enabled);
        $this->assertSame(300, $policy->maxRequestsUser);
        $this->assertSame(75, $policy->maxRequestsIP);
        $this->assertSame(120, $policy->windowSeconds);
    }

    // --- policy() factory: defaults when env vars absent ---

    #[Test]
    public function policyUsesDefaultsWhenEnvVarsAreAbsent(): void
    {
        $policy = RateLimitPolicy::policy();
        $this->assertSame(RateLimitEnabledDefault, $policy->enabled);
        $this->assertSame(RateLimitMaxRequestsUserDefault, $policy->maxRequestsUser);
        $this->assertSame(RateLimitMaxRequestsIPDefault, $policy->maxRequestsIP);
        $this->assertSame(RateLimitWindowSecondsDefault, $policy->windowSeconds);
    }

    // --- policy() factory: reads env vars ---

    #[Test]
    public function policyReadsEnabledFalseFromEnv(): void
    {
        ProcessInfo::processInfo()->environment[RateLimitEnabledKey] = 'false';
        $this->assertFalse(RateLimitPolicy::policy()->enabled);
    }

    #[Test]
    public function policyReadsEnabledTrueFromEnv(): void
    {
        ProcessInfo::processInfo()->environment[RateLimitEnabledKey] = 'true';
        $this->assertTrue(RateLimitPolicy::policy()->enabled);
    }

    #[Test]
    public function policyParsesEnabledFromNumericOne(): void
    {
        ProcessInfo::processInfo()->environment[RateLimitEnabledKey] = '1';
        $this->assertTrue(RateLimitPolicy::policy()->enabled);
    }

    #[Test]
    public function policyReadsMaxRequestsUserFromEnv(): void
    {
        ProcessInfo::processInfo()->environment[RateLimitMaxRequestsUserKey] = '200';
        $this->assertSame(200, RateLimitPolicy::policy()->maxRequestsUser);
    }

    #[Test]
    public function policyReadsMaxRequestsIPFromEnv(): void
    {
        ProcessInfo::processInfo()->environment[RateLimitMaxRequestsIPKey] = '50';
        $this->assertSame(50, RateLimitPolicy::policy()->maxRequestsIP);
    }

    #[Test]
    public function policyReadsWindowSecondsFromEnv(): void
    {
        ProcessInfo::processInfo()->environment[RateLimitWindowSecondsKey] = '300';
        $this->assertSame(300, RateLimitPolicy::policy()->windowSeconds);
    }
}
