<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Service\AccessEvaluator;
use Sabatier\Service\AccessEvaluatorChain;
use Sabatier\Service\AuthenticationEvaluator;
use Sabatier\Service\AuthenticationManager;
use Sabatier\Service\AuthorizationEvaluator;
use Sabatier\Service\JSONWebTokenAccessTimeEvaluator;
use Sabatier\Service\JSONWebTokenAudienceEvaluator;
use Sabatier\Service\JSONWebTokenEnabledEvaluator;
use Sabatier\Service\JSONWebTokenRefreshTimeEvaluator;
use Sabatier\Service\JSONWebTokenScopeEvaluator;
use Sabatier\Service\JSONWebTokenVersionEvaluator;
use Sabatier\Service\SessionAuthenticationEvaluator;

final class AuthenticationManagerChainTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = $_SERVER;
        ObjectClass::$staticAssociatedValues = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        ObjectClass::$staticAssociatedValues = [];
        parent::tearDown();
    }

    #[Test]
    public function onlyPostIsAllowed(): void
    {
        /** @var ArrayClass<string> $allowedMethods */
        $allowedMethods = new ReflectionProperty(AuthenticationManager::class, "allowedMethods")->getValue($this->manager("/login"));
        $this->assertSame([HTTPRequestMethod::post], $allowedMethods->array);
    }

    #[Test]
    public function theDefaultChainRunsEveryEvaluatorInTheDocumentedOrder(): void
    {
        $this->assertSame([
            SessionAuthenticationEvaluator::class,
            AuthenticationEvaluator::class,
            JSONWebTokenScopeEvaluator::class,
            JSONWebTokenAccessTimeEvaluator::class,
            JSONWebTokenEnabledEvaluator::class,
            JSONWebTokenVersionEvaluator::class,
            JSONWebTokenAudienceEvaluator::class,
            AuthorizationEvaluator::class,
        ], $this->evaluatorClasses("/login"));
    }

    #[Test]
    public function theRefreshChainIsShorterAndSkipsSessionAudienceAndAuthorization(): void
    {
        $this->assertSame([
            AuthenticationEvaluator::class,
            JSONWebTokenScopeEvaluator::class,
            JSONWebTokenRefreshTimeEvaluator::class,
            JSONWebTokenEnabledEvaluator::class,
            JSONWebTokenVersionEvaluator::class,
        ], $this->evaluatorClasses("/refresh"));
    }

    #[Test]
    public function logoutIsEvaluatedWithTheDefaultChainRatherThanTheRefreshOne(): void
    {
        $this->assertSame($this->evaluatorClasses("/login"), $this->evaluatorClasses("/logout"));
    }

    #[Test]
    public function theEvaluatorChainIsBuiltOnceAndMemoized(): void
    {
        $manager = $this->manager("/login");
        $this->assertSame($manager->accessEvaluator, $manager->accessEvaluator);
    }

    #[Test]
    public function theChainIsAnAccessEvaluatorChain(): void
    {
        $this->assertInstanceOf(AccessEvaluatorChain::class, $this->manager("/login")->accessEvaluator);
    }

    /** @return list<class-string<AccessEvaluator>> */
    private function evaluatorClasses(string $path): array
    {
        $evaluator = $this->manager($path)->accessEvaluator;
        $this->assertInstanceOf(AccessEvaluatorChain::class, $evaluator);
        /** @var list<class-string<AccessEvaluator>> */
        return $evaluator->evaluators->map(fn(AccessEvaluator $evaluator): string => $evaluator::class)->array;
    }

    private function manager(string $path): AuthenticationManager
    {
        ObjectClass::$staticAssociatedValues = [];
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["REQUEST_URI"] = $path;
        $_SERVER["REQUEST_METHOD"] = HTTPRequestMethod::post;
        return new AuthenticationManager();
    }
}
