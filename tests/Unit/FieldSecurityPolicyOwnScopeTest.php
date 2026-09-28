<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;
use Sabatier\Service\Authorizable;
use Sabatier\Service\AuthorizationContext;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\FieldLevelSecurityPolicy;

final class FieldSecurityPolicyOwnScopeTest extends TestCase
{
    #[Test]
    public function theOwnScopeIsAnsweredPerEntity(): void
    {
        $policy = $this->policy(new ArrayClass(["Order:read:own"]));
        $this->assertTrue($policy->hasOwnScopeFor("Order", AuthorizationType::read));
        $this->assertFalse($policy->hasOwnScopeFor("Invoice", AuthorizationType::read));
    }

    #[Test]
    public function theOwnScopeIsAnsweredPerAction(): void
    {
        $policy = $this->policy(new ArrayClass(["Order:read:own", "Order:delete:all"]));
        $this->assertTrue($policy->hasOwnScopeFor("Order", AuthorizationType::read));
        $this->assertFalse($policy->hasOwnScopeFor("Order", AuthorizationType::delete));
        $this->assertFalse($policy->hasOwnScopeFor("Order", AuthorizationType::update));
    }

    #[Test]
    public function anOwnScopeOnAnyActionRestrictsEveryAction(): void
    {
        $policy = $this->policy(new ArrayClass(["Order:any:own"]));
        $this->assertTrue($policy->hasOwnScopeFor("Order", AuthorizationType::read));
        $this->assertTrue($policy->hasOwnScopeFor("Order", AuthorizationType::delete));
    }

    #[Test]
    public function aScopeOnAllRowsWinsOverAnOwnScopeForTheSameAction(): void
    {
        $this->assertFalse($this->policy(new ArrayClass(["Order:read:own", "Order:read:all"]))->hasOwnScopeFor("Order", AuthorizationType::read));
        $this->assertFalse($this->policy(new ArrayClass(["Order:read:own", "Order:any:all"]))->hasOwnScopeFor("Order", AuthorizationType::read));
    }

    /** @param ArrayClass<string> $scopes */
    private function policy(ArrayClass $scopes): FieldLevelSecurityPolicy
    {
        return new FieldLevelSecurityPolicy(new AuthorizationContext($this->user(), $scopes, true));
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
