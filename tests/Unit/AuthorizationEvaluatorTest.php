<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\Set;
use Sabatier\Service\AccessEvaluationContext;
use Sabatier\Service\Authentication;
use Sabatier\Service\AuthenticationScheme;
use Sabatier\Service\Authorizable;
use Sabatier\Service\AuthorizationEvaluator;
use Sabatier\Service\AuthorizationResolver;
use Sabatier\Service\AuthorizationScope;
use Sabatier\Service\AuthorizationService;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\CachedAuthorization;
use Sabatier\Service\InMemoryAuthorizationCache;
use Sabatier\Service\MethodNotAllowedException;
use Sabatier\Service\PublicAccessPolicy;
use Sabatier\Service\Request;

final class AuthorizationEvaluatorTest extends TestCase
{
    #[Test]
    public function anUnauthenticatedRequestIsDeniedBeforeAnythingIsAsked(): void
    {
        $this->assertFalse(new AuthorizationEvaluator()->evaluate($this->context(HTTPRequestMethod::get, "/Order", null)));
    }

    /** @return iterable<string, array{string}> */
    public static function unsupportedMethodProvider(): iterable
    {
        yield "OPTIONS" => [HTTPRequestMethod::options];
        yield "TRACE" => ["TRACE"];
        yield "an empty method" => [""];
    }

    #[Test]
    #[DataProvider("unsupportedMethodProvider")]
    public function aMethodWithNoAuthorizationMeaningIsRefused(string $method): void
    {
        $this->expectException(MethodNotAllowedException::class);
        new AuthorizationEvaluator()->evaluate($this->context($method, "/Order", $this->user()));
    }

    /** @return iterable<string, array{string}> */
    public static function supportedMethodProvider(): iterable
    {
        yield "HEAD" => [HTTPRequestMethod::head];
        yield "GET" => [HTTPRequestMethod::get];
        yield "POST" => [HTTPRequestMethod::post];
        yield "PUT" => [HTTPRequestMethod::put];
        yield "PATCH" => [HTTPRequestMethod::patch];
        yield "DELETE" => [HTTPRequestMethod::delete];
    }

    #[Test]
    #[DataProvider("supportedMethodProvider")]
    public function everySupportedMethodReachesTheAuthorizationService(string $method): void
    {
        // The model behind this service declares no Authorization entity, so the lookup raises once
        // it gets there. Reaching that point is what shows the method mapped onto an
        // AuthorizationType rather than being refused as unsupported.
        try {
            new AuthorizationEvaluator()->evaluate($this->context($method, "/Order", $this->user()));
            $this->fail("The evaluator must reach the authorization lookup.");
        } catch (InternalInconsistencyException $exception) {
            $this->assertStringContainsString("No implementor found", (string)$exception->error->localizedFailureReason);
        }
    }

    #[Test]
    public function aUserHoldingThePermissionIsAuthorized(): void
    {
        $user = $this->user();
        new InMemoryAuthorizationCache()->setAuthorizableAuthorizations($user, new ArrayClass([new CachedAuthorization("Order", AuthorizationType::read, AuthorizationScope::all)]));
        $this->assertTrue(new AuthorizationEvaluator()->evaluate($this->context(HTTPRequestMethod::get, "/Order", $user)));
    }

    #[Test]
    public function aUserLackingThePermissionIsDenied(): void
    {
        $user = $this->user();
        new InMemoryAuthorizationCache()->setAuthorizableAuthorizations($user, new ArrayClass([new CachedAuthorization("Order", AuthorizationType::create, AuthorizationScope::all)]));
        $this->assertFalse(new AuthorizationEvaluator()->evaluate($this->context(HTTPRequestMethod::get, "/Order", $user)));
    }

    #[Test]
    public function thePublicPolicyAuthorizesWithoutAUser(): void
    {
        $context = $this->context(HTTPRequestMethod::get, "/Order", null);
        $this->assertTrue(new PublicAccessPolicy()->allowsAccess("Order", AuthorizationType::delete, null, new ArrayClass(), $context->authorizationService, $context->managedObjectContext));
    }

    #[Override]
    protected function tearDown(): void
    {
        new InMemoryAuthorizationCache()->invalidateAll();
        parent::tearDown();
    }

    private function context(string $method, string $path, ?Authorizable $user): AccessEvaluationContext
    {
        $server = $_SERVER;
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["REQUEST_URI"] = $path;
        $_SERVER["REQUEST_METHOD"] = $method === "" ? HTTPRequestMethod::get : $method;
        $request = new Request();
        $request->httpMethod = $method;
        $_SERVER = $server;
        $context = new ReflectionClass(AccessEvaluationContext::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AccessEvaluationContext::class, "request")->setValue($context, $request);
        new ReflectionProperty(AccessEvaluationContext::class, "authentication")->setValue($context, $this->authentication($user));
        $model = new ManagedObjectModel();
        $model->entities = new ArrayClass();
        new ReflectionProperty(AccessEvaluationContext::class, "authorizationService")->setValue($context, new AuthorizationService(new AuthorizationResolver($model), new InMemoryAuthorizationCache()));
        new ReflectionProperty(AccessEvaluationContext::class, "managedObjectContext")->setValue($context, new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor());
        return $context;
    }

    private function authentication(?Authorizable $user): Authentication
    {
        $authentication = new class extends Authentication {
            public function __construct()
            {
            }

            public AuthenticationScheme $scheme { get => AuthenticationScheme::bearer; }
            public ?URLCredential $credential { get => null; }
            public bool $isValid { get => true; }

            #[Override]
            public static function isSupported(AuthenticationScheme $scheme): bool
            {
                return false;
            }
        };
        new ReflectionProperty(Authentication::class, "isAuthenticatedUserResolved")->setValue($authentication, true);
        new ReflectionProperty(Authentication::class, "authenticatedUser")->setRawValue($authentication, $user);
        return $authentication;
    }

    private function user(): Authorizable
    {
        return new class implements Authorizable {
            public string $username { get => "ada"; }
            public ?string $password { get => null; }
            public bool $isEnabled { get => true; }
            public int $refreshTokenVersion { get => 1; set {} }
            public Set $roles { get => new Set(); }

            #[Override]
            public function isEqual(mixed $other): bool
            {
                return $this === $other;
            }

            #[Override]
            public static function defaultRepresentation(): Dictionary
            {
                return new Dictionary();
            }
        };
    }
}
