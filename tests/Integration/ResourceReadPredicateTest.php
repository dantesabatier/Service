<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\AuthorizationContext;
use Sabatier\Service\FieldLevelSecurityPolicy;
use Sabatier\Service\Readable;

// --- Fixtures ---

#[Readable(where: 'day == $TODAY')]
class TodayGuardedResourceFixture extends ManagedObject
{
}

#[Readable]
class BareReadableResourceFixture extends ManagedObject
{
}

class UnguardedResourceFixture extends ManagedObject
{
}

// --- Tests ---

final class ResourceReadPredicateTest extends TestCase
{
    private function makePolicy(bool $securityEnabled): FieldLevelSecurityPolicy
    {
        return new FieldLevelSecurityPolicy(new AuthorizationContext(null, new ArrayClass(), $securityEnabled));
    }

    #[Test]
    public function resolvesWherePredicateFromClassReadable(): void
    {
        $predicate = $this->makePolicy(true)->resourceReadPredicate(TodayGuardedResourceFixture::class);
        $this->assertNotNull($predicate);
        $this->assertSame('day = $TODAY', $predicate->predicateFormat);
    }

    #[Test]
    public function returnsNullWhenReadableHasNoWhere(): void
    {
        $this->assertNull($this->makePolicy(true)->resourceReadPredicate(BareReadableResourceFixture::class));
    }

    #[Test]
    public function returnsNullWhenClassCarriesNoReadable(): void
    {
        $this->assertNull($this->makePolicy(true)->resourceReadPredicate(UnguardedResourceFixture::class));
    }

    #[Test]
    public function returnsNullWhenSecurityDisabled(): void
    {
        $this->assertNull($this->makePolicy(false)->resourceReadPredicate(TodayGuardedResourceFixture::class));
    }
}
