<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\CompoundPredicate;
use Sabatier\Foundation\Set;
use Sabatier\Service\AuthorizableRole;
use Sabatier\Service\Authorizable;
use Sabatier\Service\AuthorizationContext;
use Sabatier\Service\FieldLevelSecurityPolicy;
use Sabatier\Service\Owner;
use Sabatier\Service\ReadPersistentSpaceResponseStrategy;
use Sabatier\Service\Readable;
use Sabatier\Service\Request;

final class OwnedReadEntityFixture extends ManagedObject
{
    #[Owner]
    public ?Authorizable $createdBy = null;
}

final class UnownedReadEntityFixture extends ManagedObject
{
}

#[Readable(["auditor"])]
final class RoleGuardedReadEntityFixture extends ManagedObject
{
}

final class ReadPersistentSpaceFetchRequestTest extends TestCase
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

    /** @throws ReflectionException */
    #[Test]
    public function withoutSecurityTheQueryPredicateIsTheWholeFilter(): void
    {
        $fetchRequest = $this->fetchRequest("/OwnedReadEntityFixture?status=active", OwnedReadEntityFixture::class, false);
        $this->assertSame("status = 'active'", $fetchRequest->predicate?->predicateFormat);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theEntityIsAlwaysForcedOntoTheRequest(): void
    {
        $this->assertSame("OwnedReadEntityFixture", $this->fetchRequest("/OwnedReadEntityFixture", OwnedReadEntityFixture::class, false)->entity?->name);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aBase64FetchRequestCannotRedirectTheReadToAnotherEntity(): void
    {
        $fetchRequest = $this->fetchRequest("/OwnedReadEntityFixture?fetchRequest=" . base64_encode("{\"entityName\": \"Elsewhere\"}"), OwnedReadEntityFixture::class, false);
        $this->assertSame("OwnedReadEntityFixture", $fetchRequest->entity?->name);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anOwnScopedSubjectIsNarrowedToItsOwnRows(): void
    {
        $fetchRequest = $this->fetchRequest("/OwnedReadEntityFixture", OwnedReadEntityFixture::class, true, ["OwnedReadEntityFixture"]);
        $this->assertInstanceOf(ComparisonPredicate::class, $fetchRequest->predicate);
        $this->assertStringContainsString("createdBy", $fetchRequest->predicate->predicateFormat);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theOwnershipPredicateIsAndedOntoTheQueryPredicate(): void
    {
        $fetchRequest = $this->fetchRequest("/OwnedReadEntityFixture?status=active", OwnedReadEntityFixture::class, true, ["OwnedReadEntityFixture"]);
        $this->assertInstanceOf(CompoundPredicate::class, $fetchRequest->predicate);
        $format = $fetchRequest->predicate->predicateFormat;
        $this->assertStringContainsString("status = 'active'", $format);
        $this->assertStringContainsString("createdBy", $format);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anEntityWithoutAnOwnerFieldIsNotNarrowedByOwnership(): void
    {
        $this->assertNull($this->fetchRequest("/UnownedReadEntityFixture", UnownedReadEntityFixture::class, true, ["UnownedReadEntityFixture"])->predicate);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aSubjectWithoutTheOwnScopeSeesEveryRow(): void
    {
        $this->assertNull($this->fetchRequest("/OwnedReadEntityFixture", OwnedReadEntityFixture::class, true)->predicate);
    }

    /** @throws ReflectionException */
    #[Test]
    public function securityBeingDisabledSkipsTheOwnershipNarrowing(): void
    {
        $this->assertNull($this->fetchRequest("/OwnedReadEntityFixture", OwnedReadEntityFixture::class, false, ["OwnedReadEntityFixture"])->predicate);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aResourceRuleExcludingTheSubjectNarrowsTheFetchToNoRows(): void
    {
        $fetchRequest = $this->fetchRequest("/RoleGuardedReadEntityFixture", RoleGuardedReadEntityFixture::class, true, [], "intern");
        $this->assertSame("FALSEPREDICATE", $fetchRequest->predicate?->predicateFormat);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theResourcePredicateIsAndedOntoTheQueryPredicate(): void
    {
        $fetchRequest = $this->fetchRequest("/RoleGuardedReadEntityFixture?status=active", RoleGuardedReadEntityFixture::class, true, [], "intern");
        $this->assertInstanceOf(CompoundPredicate::class, $fetchRequest->predicate);
        $this->assertStringContainsString("status = 'active'", $fetchRequest->predicate->predicateFormat);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aSubjectTheResourceRuleAdmitsIsNotNarrowed(): void
    {
        $this->assertNull($this->fetchRequest("/RoleGuardedReadEntityFixture", RoleGuardedReadEntityFixture::class, true, [], "auditor")->predicate);
    }

    /**
     * @param class-string<ManagedObject> $className
     * @param list<string> $ownScopedEntityNames
     * @throws ReflectionException
     */
    private function fetchRequest(string $requestURI, string $className, bool $isSecurityEnabled, array $ownScopedEntityNames = [], string ...$roleNames): FetchRequest
    {
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["REQUEST_URI"] = $requestURI;
        $_SERVER["REQUEST_METHOD"] = "GET";
        $entity = new EntityDescription();
        $entity->name = new ReflectionClass($className)->getShortName();
        $entity->managedObjectClassName = $className;
        $scopes = new ArrayClass($ownScopedEntityNames)->map(fn(string $entityName): string => "$entityName:read:own");
        $policy = new FieldLevelSecurityPolicy(new AuthorizationContext($this->user(...$roleNames), $scopes, $isSecurityEnabled));
        $strategy = new ReadPersistentSpaceResponseStrategy(new Request(), $entity, new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor(), $policy);
        return $strategy->fetchRequest;
    }

    private function user(string ...$roleNames): Authorizable
    {
        $roles = new Set(new ArrayClass($roleNames)->map($this->role(...))->array);
        return new class ($roles) implements Authorizable {
            public function __construct(private readonly Set $userRoles)
            {
            }

            public string $username { get => "ada"; }
            public ?string $password { get => null; }
            public bool $isEnabled { get => true; }
            public int $refreshTokenVersion { get => 1; set {} }
            public Set $roles { get => $this->userRoles; }

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

    private function role(string $name): AuthorizableRole
    {
        return new class ($name) implements AuthorizableRole {
            public function __construct(private readonly string $roleName)
            {
            }

            public string $name { get => $this->roleName; }
            public Set $authorizations { get => new Set(); }
        };
    }
}
