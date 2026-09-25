<?php

/** @noinspection PhpPossiblePolymorphicInvocationInspection */

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Service\Application;
use Sabatier\Service\Authentication;
use Sabatier\Service\AuthenticationContext;
use Sabatier\Service\AuthenticationManager;
use Sabatier\Service\AuthenticationScheme;
use Sabatier\Service\RateLimitPolicy;
use Sabatier\Service\RateLimitStore;
use Sabatier\Service\TooManyRequestsException;

/**
 * Drives the run loop's rate-limit step against an in-memory store, so the keys it counts under,
 * the headers it reports and the point at which it refuses are fixed without a backend.
 */
final class ApplicationRateLimitTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = $_SERVER;
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["REQUEST_URI"] = "/orders";
        $_SERVER["REQUEST_METHOD"] = "GET";
        $_SERVER["REMOTE_ADDR"] = "203.0.113.7";
        unset($_SERVER["HTTP_AUTHORIZATION"]);
        ObjectClass::$staticAssociatedValues = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        ObjectClass::$staticAssociatedValues = [];
        parent::tearDown();
    }

    /** @throws ReflectionException */
    #[Test]
    public function aDisabledPolicyCountsNothing(): void
    {
        [$application, $store] = $this->application(new RateLimitPolicy(enabled: false));
        $this->enforce($application);
        $this->assertSame([], $store->counts);
        $this->assertNull($application->rateLimitInfo);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anAnonymousCallerIsCountedUnderItsAddress(): void
    {
        [$application, $store] = $this->application();
        $this->enforce($application);
        $this->assertSame(["rate_limit:ip:203.0.113.7"], array_keys($store->counts));
    }

    /** @throws ReflectionException */
    #[Test]
    public function anAddressThatCannotBeResolvedIsCountedAsUnknown(): void
    {
        unset($_SERVER["REMOTE_ADDR"]);
        [$application, $store] = $this->application();
        $this->enforce($application);
        $this->assertSame(["rate_limit:ip:unknown"], array_keys($store->counts));
    }

    /** @throws ReflectionException */
    #[Test]
    public function anAnonymousCallerIsHeldToTheAddressAllowance(): void
    {
        [$application] = $this->application(new RateLimitPolicy(maxRequestsUser: 5, maxRequestsIP: 90));
        $this->enforce($application);
        $this->assertSame(90, $application->rateLimitInfo?->limit);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theRemainingAllowanceCountsDownWithEachRequest(): void
    {
        [$application] = $this->application(new RateLimitPolicy(maxRequestsIP: 10));
        $this->enforce($application);
        $this->assertSame(9, $application->rateLimitInfo?->remaining);
        $this->resetInfo($application);
        $this->enforce($application);
        $this->assertSame(8, $application->rateLimitInfo?->remaining);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theResetIsReportedAsAnAbsoluteMoment(): void
    {
        [$application, $store] = $this->application();
        $store->remaining = 30;
        $this->enforce($application);
        $this->assertEqualsWithDelta(time() + 30, $application->rateLimitInfo?->reset, 1.0);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theAllowanceNeverGoesBelowZero(): void
    {
        [$application, $store] = $this->application(new RateLimitPolicy(maxRequestsIP: 1));
        $store->seed("rate_limit:ip:203.0.113.7", 50);
        try {
            $this->enforce($application);
        } catch (TooManyRequestsException) {
        }
        $this->assertSame(0, $application->rateLimitInfo?->remaining);
    }

    /** @throws ReflectionException */
    #[Test]
    public function exhaustingTheAllowanceRefusesTheRequest(): void
    {
        [$application] = $this->application(new RateLimitPolicy(maxRequestsIP: 2));
        $this->enforce($application);
        $this->resetInfo($application);
        $this->enforce($application);
        $this->resetInfo($application);
        $this->expectException(TooManyRequestsException::class);
        $this->enforce($application);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theRefusalTellsTheCallerHowLongToWait(): void
    {
        [$application, $store] = $this->application(new RateLimitPolicy(maxRequestsIP: 1));
        $store->seed("rate_limit:ip:203.0.113.7", 50);
        $store->remaining = 17;
        try {
            $this->enforce($application);
            $this->fail("An exhausted allowance must be refused.");
        } catch (TooManyRequestsException $exception) {
            $this->assertSame(17, $exception->retryAfter);
        }
    }

    /** @throws ReflectionException */
    #[Test]
    public function aRefusalAlwaysAsksForAtLeastOneSecond(): void
    {
        [$application, $store] = $this->application(new RateLimitPolicy(maxRequestsIP: 1));
        $store->seed("rate_limit:ip:203.0.113.7", 50);
        $store->remaining = 0;
        try {
            $this->enforce($application);
            $this->fail("An exhausted allowance must be refused.");
        } catch (TooManyRequestsException $exception) {
            $this->assertSame(1, $exception->retryAfter);
        }
    }

    /** @throws ReflectionException */
    #[Test]
    public function theRequestIsCountedExactlyOncePerKey(): void
    {
        [$application, $store] = $this->application();
        $this->enforce($application);
        $this->assertSame(1, $store->counts["rate_limit:ip:203.0.113.7"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anAuthenticatedCallerIsCountedByAddressAndByIdentity(): void
    {
        [$application, $store] = $this->application(username: "ada");
        $this->enforce($application);
        $this->assertSame(["rate_limit:ip:203.0.113.7", "rate_limit:ip:203.0.113.7:user:ada"], array_keys($store->counts));
    }

    /** @throws ReflectionException */
    #[Test]
    public function anAuthenticatedCallerIsHeldToTheUserAllowanceOnBothKeys(): void
    {
        [$application] = $this->application(new RateLimitPolicy(maxRequestsUser: 5, maxRequestsIP: 90), "ada");
        $this->enforce($application);
        $this->assertSame(5, $application->rateLimitInfo?->limit);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theTightestOfTheTwoAllowancesIsTheOneReported(): void
    {
        [$application, $store] = $this->application(new RateLimitPolicy(maxRequestsUser: 10), "ada");
        $store->seed("rate_limit:ip:203.0.113.7:user:ada", 6);
        $this->enforce($application);
        $this->assertSame(3, $application->rateLimitInfo?->remaining);
    }

    /** @throws ReflectionException */
    #[Test]
    public function exhaustingEitherKeyRefusesTheRequest(): void
    {
        [$application, $store] = $this->application(new RateLimitPolicy(maxRequestsUser: 2), "ada");
        $store->seed("rate_limit:ip:203.0.113.7:user:ada", 9);
        $this->expectException(TooManyRequestsException::class);
        $this->enforce($application);
    }

    private function resetInfo(Application $application): void
    {
        new ReflectionProperty(Application::class, "rateLimitInfo")->setValue($application, null);
    }

    /** @throws ReflectionException */
    private function enforce(Application $application): void
    {
        new ReflectionMethod(Application::class, "enforceRateLimitIfNeeded")->invoke($application);
    }

    /** @throws ReflectionException */
    private function authentication(?string $username): Authentication
    {
        return new class (new ReflectionClass(AuthenticationContext::class)->newInstanceWithoutConstructor(), new Dictionary(), $username) extends Authentication {
            public function __construct(AuthenticationContext $context, Dictionary $environment, private readonly ?string $user)
            {
                parent::__construct($context, $environment);
            }

            public AuthenticationScheme $scheme { get => AuthenticationScheme::basic; }
            public ?URLCredential $credential { get => $this->user === null ? null : new URLCredential($this->user); }
            public bool $isValid { get => $this->user !== null; }

            #[Override]
            public static function isSupported(AuthenticationScheme $scheme): bool
            {
                return true;
            }
        };
    }

    /**
     * @return array{Application, RateLimitStore}
     * @throws ReflectionException
     */
    private function application(?RateLimitPolicy $policy = null, ?string $username = null): array
    {
        $store = new class implements RateLimitStore {
            /** @var array<string, int> */
            public array $counts = [];
            public int $remaining = 60;

            public function seed(string $key, int $count): void
            {
                $this->counts[$key] = $count;
            }

            #[Override]
            public function increment(string $key, int $windowSeconds): int
            {
                return $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
            }

            #[Override]
            public function ttl(string $key): int
            {
                return $this->remaining;
            }
        };
        $application = new ReflectionClass(Application::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(Application::class, "rateLimitPolicy")->setRawValue($application, $policy ?? new RateLimitPolicy());
        new ReflectionProperty(Application::class, "rateLimitStore")->setRawValue($application, $store);
        // The step reads the caller's credential, and resolving one for real would boot the Core Data stack this suite has no store for.
        $manager = new ReflectionClass(AuthenticationManager::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AuthenticationManager::class, "authentication")->setRawValue($manager, $this->authentication($username));
        new ReflectionProperty(Application::class, "authenticationManager")->setRawValue($application, $manager);
        return [$application, $store];
    }
}
