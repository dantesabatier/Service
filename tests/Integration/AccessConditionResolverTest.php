<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Nil;
use Sabatier\Service\AccessConditionResolver;

// --- Fixture ---

class DateConditionFixture extends ManagedObject
{
    public string $day = "";
}

// --- Tests ---

final class AccessConditionResolverTest extends TestCase
{
    /** @throws ReflectionException */
    private function makeResource(string $day): DateConditionFixture
    {
        /** @var DateConditionFixture $resource */
        $resource = new ReflectionClass(DateConditionFixture::class)->newInstanceWithoutConstructor();
        $entity = new ReflectionClass(EntityDescription::class)->newInstanceWithoutConstructor();
        $context = new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
        new ReflectionClass(ManagedObject::class)->getProperty("entity")->setValue($resource, $entity);
        new ReflectionClass(ManagedObject::class)->getProperty("managedObjectContext")->setValue($resource, $context);
        $resource->day = $day;
        return $resource;
    }

    #[Test]
    public function predicateParsesFormatWithArguments(): void
    {
        $predicate = new AccessConditionResolver()->predicate("%K == %@", ["day", "x"]);
        $this->assertSame("day = 'x'", $predicate->predicateFormat);
    }

    /** @throws ReflectionException */
    #[Test]
    public function evaluateResolvesTodayVariableAgainstMatchingResource(): void
    {
        $resolver = new AccessConditionResolver();
        $resource = $this->makeResource(Date::now()->format("Y-m-d"));
        $this->assertTrue($resolver->evaluate("%K == \$TODAY", ["day"], $resource));
    }

    /** @throws ReflectionException */
    #[Test]
    public function evaluateResolvesTodayVariableAgainstNonMatchingResource(): void
    {
        $resolver = new AccessConditionResolver();
        $resource = $this->makeResource("1999-01-01");
        $this->assertFalse($resolver->evaluate("%K == \$TODAY", ["day"], $resource));
    }

    /** @throws ReflectionException */
    #[Test]
    public function evaluateResolvesWeekStartVariable(): void
    {
        $resolver = new AccessConditionResolver();
        $weekStart = Date::dateWithTimeIntervalSince1970((float)strtotime("monday this week"))->format("Y-m-d");
        $this->assertTrue($resolver->evaluate("%K == \$WEEK_START", ["day"], $this->makeResource($weekStart)));
    }

    #[Test]
    public function variablesBindTheRemoteAddress(): void
    {
        $_SERVER["REMOTE_ADDR"] = "203.0.113.7";
        $this->assertSame("203.0.113.7", new AccessConditionResolver()->variables["\$REMOTE_ADDRESS"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function evaluateResolvesRemoteAddressVariable(): void
    {
        $_SERVER["REMOTE_ADDR"] = "203.0.113.7";
        $resolver = new AccessConditionResolver();
        $this->assertTrue($resolver->evaluate("\$REMOTE_ADDRESS == %@", ["203.0.113.7"], $this->makeResource("")));
        $this->assertFalse($resolver->evaluate("\$REMOTE_ADDRESS == %@", ["198.51.100.1"], $this->makeResource("")));
        $this->assertFalse($resolver->evaluate("\$REMOTE_ADDRESS == %@", [Nil::nil()], $this->makeResource("")));
    }

    #[Test]
    public function variablesBindNilForAnUnavailableRemoteAddress(): void
    {
        unset($_SERVER["REMOTE_ADDR"]);
        $this->assertSame(Nil::nil(), new AccessConditionResolver()->variables["\$REMOTE_ADDRESS"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function evaluateResolvesAnUnavailableRemoteAddressToNull(): void
    {
        unset($_SERVER["REMOTE_ADDR"]);
        $resolver = new AccessConditionResolver();
        $this->assertTrue($resolver->evaluate("\$REMOTE_ADDRESS == %@", [Nil::nil()], $this->makeResource("")));
        $this->assertFalse($resolver->evaluate("\$REMOTE_ADDRESS == %@", ["203.0.113.7"], $this->makeResource("")));
    }

    /** @throws ReflectionException */
    #[Test]
    public function evaluatePlainArgumentConditionWithoutVariables(): void
    {
        $resolver = new AccessConditionResolver();
        $this->assertTrue($resolver->evaluate("%K == %@", ["day", "2020-05-05"], $this->makeResource("2020-05-05")));
        $this->assertFalse($resolver->evaluate("%K == %@", ["day", "2020-05-05"], $this->makeResource("2020-01-01")));
    }
}
