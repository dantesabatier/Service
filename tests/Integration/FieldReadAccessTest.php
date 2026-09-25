<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Exception;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;
use Sabatier\Service\Authorizable;
use Sabatier\Service\AuthorizableRole;
use Sabatier\Service\AuthorizationContext;
use Sabatier\Service\AuthorizationScope;
use Sabatier\Service\FieldLevelSecurityPolicy;
use Sabatier\Service\ForbiddenException;
use Sabatier\Service\Owner;
use Sabatier\Service\Readable;

// --- Fixtures ---

class FieldReadFixture extends ManagedObject
{
    #[Owner]
    public ?Authorizable $createdBy = null;
    public string $title = "";
    #[Readable]
    public string $reference = "";
    #[Readable(["Finance"])]
    public float $salary = 0.0;
    #[Readable(where: "status == %@", arguments: ["published"])]
    public float $score = 0.0;
    #[Readable(["Finance"], AuthorizationScope::own)]
    public float $bonus = 0.0;
}

// --- Tests ---

/**
 * Covers `enforceFieldRead`, the field-level `#[Readable]` guard for a caller that reads a column
 * without materializing the row behind it — an aggregate or a grouping, where the row-filtering
 * `applySecureRead` never runs.
 *
 * A rule that cannot be evaluated without a row (`where`, or `own`) is refused rather than passed:
 * the column is the whole result, so there is nothing to filter out of it.
 */
final class FieldReadAccessTest extends TestCase
{
    private function makeRole(string $name): AuthorizableRole
    {
        return new class($name) implements AuthorizableRole {
            public function __construct(private readonly string $roleName) {}
            public string $name { get => $this->roleName; }
            public Set $authorizations { get => new Set(); }
        };
    }

    private function makeUser(string ...$roleNames): Authorizable
    {
        $roles = new Set(array_map(fn(string $name): AuthorizableRole => $this->makeRole($name), $roleNames));
        return new class($roles) implements Authorizable {
            public function __construct(private readonly Set $userRoles) {}
            public string $username { get => "user"; }
            public ?string $password { get => null; }
            public bool $isEnabled { get => true; }
            public int $refreshTokenVersion { get => 1; set {} }
            public Set $roles { get => $this->userRoles; }
            #[Override]
            public function isEqual(mixed $other): bool { return $this === $other; }
            #[Override]
            public static function defaultRepresentation(): Dictionary { return new Dictionary(); }
        };
    }

    private function makePolicy(?Authorizable $user, bool $securityEnabled = true): FieldLevelSecurityPolicy
    {
        return new FieldLevelSecurityPolicy(new AuthorizationContext($user, new ArrayClass(), $securityEnabled));
    }

    /** @throws Exception */
    #[Test]
    public function fieldWithoutReadableIsUnrestricted(): void
    {
        $this->expectNotToPerformAssertions();
        $this->makePolicy($this->makeUser("Sales"))->enforceFieldRead(FieldReadFixture::class, "title", "title");
    }

    /** @throws Exception */
    #[Test]
    public function readableWithoutRolesAllowsAnyRole(): void
    {
        $this->expectNotToPerformAssertions();
        $this->makePolicy($this->makeUser("Sales"))->enforceFieldRead(FieldReadFixture::class, "reference", "reference");
    }

    /** @throws Exception */
    #[Test]
    public function roleGuardedFieldAllowsMatchingRole(): void
    {
        $this->expectNotToPerformAssertions();
        $this->makePolicy($this->makeUser("Finance"))->enforceFieldRead(FieldReadFixture::class, "salary", "salary");
    }

    /** @throws Exception */
    #[Test]
    public function roleGuardedFieldDeniesOtherRole(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage("permission to read \"salary\"");
        $this->makePolicy($this->makeUser("Sales"))->enforceFieldRead(FieldReadFixture::class, "salary", "salary");
    }

    /** @throws Exception */
    #[Test]
    public function conditionalFieldIsDeniedBecauseNoRowIsAvailable(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->makePolicy($this->makeUser("Sales"))->enforceFieldRead(FieldReadFixture::class, "score", "score");
    }

    /** @throws Exception */
    #[Test]
    public function ownScopedFieldIsDeniedEvenForTheDeclaredRole(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->makePolicy($this->makeUser("Finance"))->enforceFieldRead(FieldReadFixture::class, "bonus", "bonus");
    }

    /** @throws Exception */
    #[Test]
    public function theKeyPathIsReportedRatherThanTheFieldName(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage("permission to read \"employee.salary\"");
        $this->makePolicy($this->makeUser("Sales"))->enforceFieldRead(FieldReadFixture::class, "salary", "employee.salary");
    }

    /** @throws Exception */
    #[Test]
    public function nothingIsEnforcedWhenSecurityIsDisabled(): void
    {
        $this->expectNotToPerformAssertions();
        $this->makePolicy($this->makeUser("Sales"), false)->enforceFieldRead(FieldReadFixture::class, "salary", "salary");
    }

    /** @throws Exception */
    #[Test]
    public function roleGuardedFieldDeniesUnauthenticatedSubject(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->makePolicy(null)->enforceFieldRead(FieldReadFixture::class, "salary", "salary");
    }
}
