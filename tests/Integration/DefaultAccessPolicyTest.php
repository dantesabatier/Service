<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Service\AccessEvaluator;
use Sabatier\Service\AccessEvaluatorChain;
use Sabatier\Service\AuthenticationEvaluator;
use Sabatier\Service\AuthenticationManager;
use Sabatier\Service\AuthorizationEvaluator;
use Sabatier\Service\DefaultAccessPolicy;
use Sabatier\Service\ForbiddenException;
use Sabatier\Service\Responder;
use Sabatier\Service\UnauthorizedException;

final class AccessProbeResponder extends Responder
{
    public bool $available = false;

    #[Override]
    public bool $isProtectedContentAvailable {
        get => $this->available;
    }
}

final class DefaultAccessPolicyTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = $_SERVER;
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["REQUEST_URI"] = "/orders";
        $_SERVER["REQUEST_METHOD"] = HTTPRequestMethod::get;
        ObjectClass::$staticAssociatedValues = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        ObjectClass::$staticAssociatedValues = [];
        parent::tearDown();
    }

    #[Test]
    public function aResponderServingPublicContentIsLetThrough(): void
    {
        $responder = new AccessProbeResponder();
        $responder->available = true;
        new DefaultAccessPolicy()->enforceAccess($responder, $this->manager(false, null));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function anAuthenticatedManagerIsLetThroughEvenForAGuardedResponder(): void
    {
        new DefaultAccessPolicy()->enforceAccess(new AccessProbeResponder(), $this->manager(true, null));
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function aSubjectTheAuthorizationEvaluatorRejectedIsForbidden(): void
    {
        $this->expectException(ForbiddenException::class);
        new DefaultAccessPolicy()->enforceAccess(new AccessProbeResponder(), $this->manager(false, new AuthorizationEvaluator()));
    }

    /** @return iterable<string, array{string, string}> */
    public static function methodProvider(): iterable
    {
        yield "a read names the resource" => [HTTPRequestMethod::get, "access this resource"];
        yield "a write names the action" => [HTTPRequestMethod::post, "perform this action"];
        yield "a delete names the action" => [HTTPRequestMethod::delete, "perform this action"];
    }

    #[Test]
    #[DataProvider("methodProvider")]
    public function theRefusalIsWordedForWhatTheCallerTriedToDo(string $method, string $expected): void
    {
        $_SERVER["REQUEST_METHOD"] = $method;
        ObjectClass::$staticAssociatedValues = [];
        try {
            new DefaultAccessPolicy()->enforceAccess(new AccessProbeResponder(), $this->manager(false, new AuthorizationEvaluator()));
            $this->fail("An unauthorized subject must be refused.");
        } catch (ForbiddenException $exception) {
            $this->assertStringContainsString($expected, (string)$exception->error->localizedFailureReason);
        }
    }

    #[Test]
    public function anyOtherFailedEvaluatorLeavesTheCallerUnauthenticated(): void
    {
        $this->expectException(UnauthorizedException::class);
        new DefaultAccessPolicy()->enforceAccess(new AccessProbeResponder(), $this->manager(false, new AuthenticationEvaluator()));
    }

    #[Test]
    public function theRefusalNamesTheEvaluatorThatRejectedTheRequest(): void
    {
        try {
            new DefaultAccessPolicy()->enforceAccess(new AccessProbeResponder(), $this->manager(false, new AuthenticationEvaluator()));
            $this->fail("An unauthenticated subject must be refused.");
        } catch (UnauthorizedException $exception) {
            $reason = (string)$exception->error->localizedFailureReason;
            $this->assertStringContainsString("AuthenticationEvaluator", $reason);
            $this->assertStringContainsString("rejected the request", $reason);
        }
    }

    #[Test]
    public function aChainThatNamedNoFailedEvaluatorStillRefuses(): void
    {
        try {
            new DefaultAccessPolicy()->enforceAccess(new AccessProbeResponder(), $this->manager(false, null));
            $this->fail("A request no evaluator allowed must be refused.");
        } catch (UnauthorizedException $exception) {
            $this->assertStringContainsString("no evaluator allowed access", (string)$exception->error->localizedFailureReason);
        }
    }

    private function manager(bool $isProtectedContentAvailable, ?AccessEvaluator $failedEvaluator): AuthenticationManager
    {
        $chain = new AccessEvaluatorChain(new ArrayClass());
        new ReflectionProperty(AccessEvaluatorChain::class, "failedEvaluator")->setRawValue($chain, $failedEvaluator);
        $manager = new ReflectionClass(AuthenticationManager::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AuthenticationManager::class, "accessEvaluator")->setRawValue($manager, $chain);
        new ReflectionProperty(Responder::class, "isProtectedContentAvailable")->setRawValue($manager, $isProtectedContentAvailable);
        return $manager;
    }
}
