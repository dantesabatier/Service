<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Date;
use Sabatier\Service\AccessConditionResolver;

// --- Fixture ---

class DateConditionFixture extends ManagedObject
{
    public string $day = '';
}

// --- Tests ---

final class AccessConditionResolverTest extends TestCase
{
    private function makeResource(string $day): DateConditionFixture
    {
        /** @var DateConditionFixture $resource */
        $resource = (new ReflectionClass(DateConditionFixture::class))->newInstanceWithoutConstructor();
        $entity = (new ReflectionClass(EntityDescription::class))->newInstanceWithoutConstructor();
        $context = (new ReflectionClass(ManagedObjectContext::class))->newInstanceWithoutConstructor();
        (new ReflectionClass(ManagedObject::class))->getProperty('entity')->setValue($resource, $entity);
        (new ReflectionClass(ManagedObject::class))->getProperty('managedObjectContext')->setValue($resource, $context);
        $resource->day = $day;
        return $resource;
    }

    #[Test]
    public function predicateParsesFormatWithArguments(): void
    {
        $predicate = (new AccessConditionResolver())->predicate('%K == %@', ['day', 'x']);
        $this->assertSame("day = 'x'", $predicate->predicateFormat);
    }

    #[Test]
    public function evaluateResolvesTodayVariableAgainstMatchingResource(): void
    {
        $resolver = new AccessConditionResolver();
        $resource = $this->makeResource(Date::now()->format('Y-m-d'));
        $this->assertTrue($resolver->evaluate('%K == $TODAY', ['day'], $resource));
    }

    #[Test]
    public function evaluateResolvesTodayVariableAgainstNonMatchingResource(): void
    {
        $resolver = new AccessConditionResolver();
        $resource = $this->makeResource('1999-01-01');
        $this->assertFalse($resolver->evaluate('%K == $TODAY', ['day'], $resource));
    }

    #[Test]
    public function evaluateResolvesWeekStartVariable(): void
    {
        $resolver = new AccessConditionResolver();
        $weekStart = Date::dateWithTimeIntervalSince1970((float)strtotime('monday this week'))->format('Y-m-d');
        $this->assertTrue($resolver->evaluate('%K == $WEEK_START', ['day'], $this->makeResource($weekStart)));
    }

    #[Test]
    public function evaluatePlainArgumentConditionWithoutVariables(): void
    {
        $resolver = new AccessConditionResolver();
        $this->assertTrue($resolver->evaluate('%K == %@', ['day', '2020-05-05'], $this->makeResource('2020-05-05')));
        $this->assertFalse($resolver->evaluate('%K == %@', ['day', '2020-05-05'], $this->makeResource('2020-01-01')));
    }
}
