<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;
use Sabatier\Service\Application;
use Sabatier\Service\Authorizable;
use Sabatier\Service\AuthorizationResolver;
use Sabatier\Service\AuthorizationScope;
use Sabatier\Service\AuthorizationService;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\CachedAuthorization;
use Sabatier\Service\InMemoryAuthorizationCache;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\AuthorizationRequirement;
use Sabatier\Service\MCP\Tools\AuthorizationRequirements;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Sabatier\Service\MCP\Tools\ToolResult;
use Sabatier\Service\PublicAccessPolicy;
use Sabatier\Service\RequestSecurityContext;
use Sabatier\Service\Testing\FixesRequestSecurityContext;

final class ToolRegistryAuthorizationTest extends TestCase
{
    use FixesRequestSecurityContext;

    private ?AuthorizationService $previousAuthorizationService = null;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $property = new ReflectionProperty(Application::class, "authorizationService");
        $this->previousAuthorizationService = $property->isInitialized(Application::shared()) ? $property->getRawValue(Application::shared()) : null;
        $resolver = new ReflectionClass(AuthorizationResolver::class)->newInstanceWithoutConstructor();
        $property->setRawValue(Application::shared(), new AuthorizationService($resolver, new InMemoryAuthorizationCache()));
    }

    #[Override]
    protected function tearDown(): void
    {
        new InMemoryAuthorizationCache()->invalidateAll();
        $property = new ReflectionProperty(Application::class, "authorizationService");
        if ($this->previousAuthorizationService !== null) {
            $property->setRawValue(Application::shared(), $this->previousAuthorizationService);
        }
        parent::tearDown();
    }

    #[Test]
    public function aCallWithNoSecurityContextIsDenied(): void
    {
        $result = $this->call(AuthorizationRequirements::none());
        $this->assertTrue($result->isError);
        $this->assertSame("No security context is in effect, so tools cannot be called. Do not retry this call.", $result->text);
    }

    #[Test]
    public function aCallWithNoSecurityContextRunsWhenTheApplicationHasNoSecurity(): void
    {
        $previous = Application::shared()->accessPolicy;
        Application::shared()->accessPolicy = new PublicAccessPolicy();
        try {
            $this->assertSame("ran", $this->call(null)->text);
        } finally {
            Application::shared()->accessPolicy = $previous;
        }
    }

    #[Test]
    public function aDisabledContextChecksNothing(): void
    {
        $this->fixRequestSecurityContext($this->unrestrictedRequestSecurityContext());
        $this->assertSame("ran", $this->call(null)->text);
    }

    #[Test]
    public function aPublicResponderDoesNotSwitchOffToolAuthorization(): void
    {
        $managedObjectContext = new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
        $this->fixRequestSecurityContext(new RequestSecurityContext(null, new ArrayClass(), true, $managedObjectContext, true));
        $this->assertSame("You must be authenticated to perform this action. Do not retry this call.", $this->call(AuthorizationRequirements::one("Order", AuthorizationType::read))->text);
    }

    #[Test]
    public function aToolCallUnderAPublicResponderHasItsWritesChecked(): void
    {
        $managedObjectContext = new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
        $this->fixRequestSecurityContext(new RequestSecurityContext(null, new ArrayClass(), true, $managedObjectContext, true));
        $this->call(AuthorizationRequirements::none());
        $this->assertTrue(DeclaringProbeTool::$enforcedWrites);
        $this->assertFalse(RequestSecurityContext::current()?->enforcesWrites);
    }

    #[Test]
    public function anUndeclaredToolIsDenied(): void
    {
        $this->fixRequestSecurityContext($this->restrictedRequestSecurityContext($this->user("undeclared")));
        $this->assertSame("probe does not declare what it must be authorized for, so it cannot be called. Do not retry this call.", $this->call(null)->text);
    }

    #[Test]
    public function anEmptyDeclarationIsDenied(): void
    {
        $this->fixRequestSecurityContext($this->restrictedRequestSecurityContext($this->user("empty")));
        $this->assertTrue($this->call(AuthorizationRequirements::of(new ArrayClass()))->isError);
    }

    #[Test]
    public function anExplicitNoneRuns(): void
    {
        $this->fixRequestSecurityContext($this->restrictedRequestSecurityContext(null));
        $this->assertSame("ran", $this->call(AuthorizationRequirements::none())->text);
    }

    #[Test]
    public function aDeclaredPermissionNeedsASubject(): void
    {
        $this->fixRequestSecurityContext($this->restrictedRequestSecurityContext(null));
        $this->assertSame("You must be authenticated to perform this action. Do not retry this call.", $this->call(AuthorizationRequirements::one("Order", AuthorizationType::read))->text);
    }

    #[Test]
    public function aDeclaredPermissionTheSubjectHoldsRuns(): void
    {
        $user = $this->user("holder");
        new InMemoryAuthorizationCache()->setAuthorizableAuthorizations($user, new ArrayClass([new CachedAuthorization("Order", AuthorizationType::read, AuthorizationScope::all)]));
        $this->fixRequestSecurityContext($this->restrictedRequestSecurityContext($user));
        $this->assertSame("ran", $this->call(AuthorizationRequirements::one("Order", AuthorizationType::read))->text);
    }

    #[Test]
    public function aTokenScopeGrantsTheDeclaredPermission(): void
    {
        $this->fixRequestSecurityContext($this->restrictedRequestSecurityContext($this->user("scoped"), new ArrayClass(["Order:read"])));
        $this->assertSame("ran", $this->call(AuthorizationRequirements::one("Order", AuthorizationType::read))->text);
    }

    #[Test]
    public function aMissingPermissionIsDeniedByName(): void
    {
        $user = $this->user("partial");
        new InMemoryAuthorizationCache()->setAuthorizableAuthorizations($user, new ArrayClass([new CachedAuthorization("Order", AuthorizationType::read, AuthorizationScope::all)]));
        $this->fixRequestSecurityContext($this->restrictedRequestSecurityContext($user));
        $requirements = AuthorizationRequirements::of(new ArrayClass([new AuthorizationRequirement("Order", AuthorizationType::read), new AuthorizationRequirement("Customer", AuthorizationType::read)]));
        $result = $this->call($requirements);
        $this->assertTrue($result->isError);
        $this->assertSame("You don't have permission to read \"Customer\". Do not retry this call.", $result->text);
    }

    private function call(?AuthorizationRequirements $requirements): ToolResult
    {
        /** @var DeclaringProbeTool $tool */
        $tool = new ReflectionClass(DeclaringProbeTool::class)->newInstanceWithoutConstructor();
        $tool->requirements = $requirements;
        return new ToolRegistry(new ArrayClass([$tool]))->call("probe", new Dictionary());
    }

    private function user(string $username): Authorizable
    {
        return new class($username) implements Authorizable {
            public function __construct(private readonly string $_username)
            {
            }
            public string $username {
                get => $this->_username;
            }
            public ?string $password {
                get => null;
            }
            public bool $isEnabled {
                get => true;
            }
            public int $refreshTokenVersion {
                get => 1;
                set {
                }
            }
            public Set $roles {
                get => new Set();
            }
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

final class DeclaringProbeTool extends AbstractTool
{
    public static bool $enforcedWrites = false;
    public ?AuthorizationRequirements $requirements = null;

    #[Override]
    public string $name {
        get => "probe";
    }
    #[Override]
    public string $description {
        get => "Probe tool.";
    }
    #[Override]
    public array $inputSchema {
        get => ["type" => "object"];
    }

    #[Override]
    public function authorizationRequirements(Dictionary $arguments): ?AuthorizationRequirements
    {
        return $this->requirements;
    }

    /** @return ArrayClass<ContentItem> */
    #[Override]
    public function execute(Dictionary $arguments): ArrayClass
    {
        self::$enforcedWrites = RequestSecurityContext::current()?->enforcesWrites ?? false;
        return new ArrayClass([new ContentItem("text", "ran")]);
    }
}
