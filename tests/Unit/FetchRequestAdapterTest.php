<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\ExpressionDescription;
use Sabatier\CoreData\FetchRequestResultType;
use Sabatier\Foundation\SortDescriptor;
use Sabatier\Service\FetchRequestAdapter;

final class FetchRequestAdapterTest extends TestCase
{
    #[Test]
    public function anEmptyRepresentationYieldsTheFetchRequestDefaults(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{}"))->fetchRequest;
        $this->assertNull($fetchRequest->predicate);
        $this->assertTrue($fetchRequest->includesSubentities);
        $this->assertSame(0, $fetchRequest->fetchLimit);
        $this->assertSame(0, $fetchRequest->fetchOffset);
        $this->assertSame(0, $fetchRequest->fetchBatchSize);
        $this->assertNull($fetchRequest->sortDescriptors);
        $this->assertSame(FetchRequestResultType::managedObjectResultType, $fetchRequest->resultType);
        $this->assertNull($fetchRequest->propertiesToFetch);
        $this->assertFalse($fetchRequest->returnsDistinctResults);
        $this->assertNull($fetchRequest->propertiesToGroupBy);
        $this->assertNull($fetchRequest->havingPredicate);
    }

    #[Test]
    public function carriesTheEntityNameAcross(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{\"entityName\": \"Order\"}"))->fetchRequest;
        $this->assertSame("Order", $fetchRequest->entityName);
    }

    #[Test]
    public function buildsThePredicateFromItsFormatAndArguments(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{\"predicate\": {\"format\": \"name == %@\", \"arguments\": [\"Ada\"]}}"))->fetchRequest;
        $this->assertNotNull($fetchRequest->predicate);
        $this->assertStringContainsString("Ada", $fetchRequest->predicate->predicateFormat);
    }

    #[Test]
    public function buildsThePredicateWithoutArguments(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{\"predicate\": {\"format\": \"enabled == 1\"}}"))->fetchRequest;
        $this->assertNotNull($fetchRequest->predicate);
        $this->assertStringContainsString("enabled", $fetchRequest->predicate->predicateFormat);
    }

    #[Test]
    public function ignoresAPredicateThatIsNotAnObject(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{\"predicate\": \"name == 1\"}"))->fetchRequest;
        $this->assertNull($fetchRequest->predicate);
    }

    #[Test]
    public function ignoresAPredicateObjectWithoutAFormat(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{\"predicate\": {\"arguments\": [\"Ada\"]}}"))->fetchRequest;
        $this->assertNull($fetchRequest->predicate);
    }

    #[Test]
    public function carriesTheScalarBoundsAcross(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{\"includesSubentities\": false, \"fetchLimit\": 10, \"fetchOffset\": 5, \"fetchBatchSize\": 3, \"returnsDistinctResults\": true}"))->fetchRequest;
        $this->assertFalse($fetchRequest->includesSubentities);
        $this->assertSame(10, $fetchRequest->fetchLimit);
        $this->assertSame(5, $fetchRequest->fetchOffset);
        $this->assertSame(3, $fetchRequest->fetchBatchSize);
        $this->assertTrue($fetchRequest->returnsDistinctResults);
    }

    #[Test]
    public function buildsSortDescriptorsAndDefaultsThemToAscending(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{\"sortDescriptors\": [{\"key\": \"name\", \"ascending\": false}, {\"key\": \"date\"}]}"))->fetchRequest;
        $sortDescriptors = $fetchRequest->sortDescriptors;
        $this->assertSame(2, $sortDescriptors?->count);
        /** @var SortDescriptor $first */
        $first = $sortDescriptors[0];
        $this->assertSame("name", $first->key);
        $this->assertFalse($first->ascending);
        /** @var SortDescriptor $second */
        $second = $sortDescriptors[1];
        $this->assertSame("date", $second->key);
        $this->assertTrue($second->ascending);
    }

    #[Test]
    public function dropsSortDescriptorsWithoutAKey(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{\"sortDescriptors\": [{\"ascending\": true}, {\"key\": \"name\"}]}"))->fetchRequest;
        $this->assertSame(1, $fetchRequest->sortDescriptors?->count);
    }

    #[Test]
    public function ignoresSortDescriptorsThatAreNotAnArray(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{\"sortDescriptors\": {\"key\": \"name\"}}"))->fetchRequest;
        $this->assertNull($fetchRequest->sortDescriptors);
    }

    #[Test]
    public function resolvesTheResultTypeFromItsRawValue(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation(sprintf("{\"resultType\": %d}", FetchRequestResultType::dictionaryResultType->value)))->fetchRequest;
        $this->assertSame(FetchRequestResultType::dictionaryResultType, $fetchRequest->resultType);
    }

    #[Test]
    public function keepsAStringPropertyToFetchVerbatim(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{\"propertiesToFetch\": [\"name\", \"date\"]}"))->fetchRequest;
        $this->assertSame(["name", "date"], $fetchRequest->propertiesToFetch?->array);
    }

    #[Test]
    public function buildsAnExpressionDescriptionFromAPropertyToFetch(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation(sprintf("{\"propertiesToFetch\": [{\"name\": \"total\", \"expression\": {\"format\": \"sum:(%%K)\", \"arguments\": [\"amount\"]}, \"resultType\": %d}]}", AttributeType::decimal->value)))->fetchRequest;
        $description = $fetchRequest->propertiesToFetch?->first;
        $this->assertInstanceOf(ExpressionDescription::class, $description);
        $this->assertSame("total", $description->name);
        $this->assertSame(AttributeType::decimal, $description->resultType);
        $this->assertNotNull($description->expression);
    }

    #[Test]
    public function leavesTheExpressionResultTypeUndefinedWhenItIsAbsent(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{\"propertiesToFetch\": [{\"name\": \"total\", \"expression\": {\"format\": \"sum:(%K)\", \"arguments\": [\"amount\"]}}]}"))->fetchRequest;
        $description = $fetchRequest->propertiesToFetch?->first;
        $this->assertInstanceOf(ExpressionDescription::class, $description);
        $this->assertSame(AttributeType::undefined, $description->resultType);
    }

    #[Test]
    public function dropsPropertiesToFetchThatAreNeitherStringsNorObjects(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{\"propertiesToFetch\": [17, true, \"name\"]}"))->fetchRequest;
        $this->assertSame(["name"], $fetchRequest->propertiesToFetch?->array);
    }

    #[Test]
    public function dropsAnExpressionPropertyMissingItsNameOrExpression(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{\"propertiesToFetch\": [{\"expression\": {\"format\": \"sum:(%K)\"}}, {\"name\": \"total\"}, {\"name\": \"total\", \"expression\": \"sum:(amount)\"}]}"))->fetchRequest;
        $this->assertTrue($fetchRequest->propertiesToFetch?->isEmpty);
    }

    #[Test]
    public function dropsAnExpressionPropertyWhoseExpressionHasNoFormat(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{\"propertiesToFetch\": [{\"name\": \"total\", \"expression\": {\"arguments\": [\"amount\"]}}]}"))->fetchRequest;
        $this->assertTrue($fetchRequest->propertiesToFetch?->isEmpty);
    }

    #[Test]
    public function ignoresPropertiesToFetchThatAreNotAnArray(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{\"propertiesToFetch\": \"name\"}"))->fetchRequest;
        $this->assertNull($fetchRequest->propertiesToFetch);
    }

    #[Test]
    public function buildsThePropertiesToGroupByThroughTheSameTransform(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{\"propertiesToGroupBy\": [\"status\", {\"name\": \"total\", \"expression\": {\"format\": \"sum:(%K)\", \"arguments\": [\"amount\"]}}]}"))->fetchRequest;
        $propertiesToGroupBy = $fetchRequest->propertiesToGroupBy;
        $this->assertSame(2, $propertiesToGroupBy?->count);
        $this->assertSame("status", $propertiesToGroupBy[0]);
        $this->assertInstanceOf(ExpressionDescription::class, $propertiesToGroupBy[1]);
    }

    #[Test]
    public function ignoresPropertiesToGroupByThatAreNotAnArray(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{\"propertiesToGroupBy\": \"status\"}"))->fetchRequest;
        $this->assertNull($fetchRequest->propertiesToGroupBy);
    }

    #[Test]
    public function buildsTheHavingPredicateFromItsFormatAndArguments(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{\"havingPredicate\": {\"format\": \"total > %@\", \"arguments\": [10]}}"))->fetchRequest;
        $this->assertNotNull($fetchRequest->havingPredicate);
        $this->assertStringContainsString("10", $fetchRequest->havingPredicate->predicateFormat);
    }

    #[Test]
    public function ignoresAHavingPredicateThatIsNotAnObject(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{\"havingPredicate\": \"total > 10\"}"))->fetchRequest;
        $this->assertNull($fetchRequest->havingPredicate);
    }

    #[Test]
    public function ignoresAHavingPredicateObjectWithoutAFormat(): void
    {
        $fetchRequest = new FetchRequestAdapter($this->representation("{\"havingPredicate\": {\"arguments\": [10]}}"))->fetchRequest;
        $this->assertNull($fetchRequest->havingPredicate);
    }

    #[Test]
    public function buildsAFreshFetchRequestOnEveryRead(): void
    {
        $adapter = new FetchRequestAdapter($this->representation("{\"entityName\": \"Order\"}"));
        $this->assertNotSame($adapter->fetchRequest, $adapter->fetchRequest);
    }

    private function representation(string $json): object
    {
        /** @var object */
        return json_decode($json, false, 512, JSON_THROW_ON_ERROR);
    }
}
