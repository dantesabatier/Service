<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\PersistentHistoryChangeRequest;
use Sabatier\CoreData\PersistentHistoryResult;
use Sabatier\CoreData\PersistentHistoryResultType;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Number;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\Tools\PersistentHistoryTool;
use const Sabatier\Service\ServiceResponseCountKey;
use const Sabatier\Service\ServiceResponseStatusKey;

/**
 * Fixes how the tool turns its arguments into a `PersistentHistoryChangeRequest`, and how it shapes
 * the result. Executing the request is not exercised: that needs a store with history tracking on.
 */
final class PersistentHistoryToolRequestTest extends TestCase
{
    #[Test]
    public function anOperationIsRequiredBeforeAnythingElseHappens(): void
    {
        try {
            $this->tool()->execute(new Dictionary(["date" => "2026-01-15"]));
            $this->fail("A call without an operation must be refused.");
        } catch (InternalInconsistencyException $exception) {
            $this->assertStringContainsString("operation is required", (string)$exception->error->localizedFailureReason);
        }
    }

    #[Test]
    public function anUnknownOperationIsRefusedAndNamesTheAllowedOnes(): void
    {
        try {
            $this->tool()->execute(new Dictionary(["operation" => "truncate"]));
            $this->fail("An unknown operation must be refused.");
        } catch (InternalInconsistencyException $exception) {
            $reason = (string)$exception->error->localizedFailureReason;
            $this->assertStringContainsString("truncate", $reason);
            $this->assertStringContainsString("fetch, purge", $reason);
        }
    }

    #[Test]
    public function anOperationThatOnlyMatchesLooselyIsStillRefused(): void
    {
        try {
            $this->tool()->execute(new Dictionary(["operation" => true]));
            $this->fail("An operation that is not one of the two names must be refused.");
        } catch (InternalInconsistencyException $exception) {
            // `true` compares equal to any non-empty operation name under a loose in_array, and a
            // looser check would let it through to fail later for an unrelated reason.
            $this->assertStringContainsString("Invalid operation", (string)$exception->error->localizedFailureReason);
        }
    }

    #[Test]
    public function theToolAdvertisesItselfAsReadOnlyOnlyForAFetch(): void
    {
        $tool = $this->tool();
        $this->assertTrue($tool->isReadOnlyCall(new Dictionary(["operation" => "fetch"])));
        $this->assertFalse($tool->isReadOnlyCall(new Dictionary(["operation" => "purge"])));
    }

    #[Test]
    public function aPurgeScopedByDateDeletesBeforeThatDate(): void
    {
        $request = $this->purgeRequest(["date" => "2026-01-15"]);
        $this->assertTrue($request->isDelete);
        $this->assertSame("2026-01-15", $request->date?->description ? substr((string)$request->date->description, 0, 10) : null);
    }

    #[Test]
    public function aPurgeScopedByTransactionDeletesBeforeThatNumber(): void
    {
        $request = $this->purgeRequest(["transaction" => "42"]);
        $this->assertTrue($request->isDelete);
        $this->assertSame(42, $request->transactionNumber?->intValue);
    }

    #[Test]
    public function aPurgeKeepsZeroAsAnExplicitTransactionBoundary(): void
    {
        $this->assertSame(0, $this->purgeRequest(["transaction" => 0])->transactionNumber?->intValue);
    }

    #[Test]
    public function aPurgeScopedByTokenDeletesBeforeThatToken(): void
    {
        $request = $this->purgeRequest(["token" => new Dictionary(["store-1" => 7])]);
        $this->assertTrue($request->isDelete);
        $this->assertNotNull($request->token);
    }

    #[Test]
    public function aPurgeWithoutAScopeIsRefused(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->purgeRequest([]);
    }

    #[Test]
    public function aFetchScopedByDateReadsAfterThatDate(): void
    {
        $request = $this->fetchRequestFor(["date" => "2026-01-15"]);
        $this->assertFalse($request->isDelete);
        $this->assertNotNull($request->date);
    }

    #[Test]
    public function aFetchScopedByTransactionReadsAfterThatNumber(): void
    {
        $request = $this->fetchRequestFor(["transaction" => "42"]);
        $this->assertFalse($request->isDelete);
        $this->assertSame(42, $request->transactionNumber?->intValue);
    }

    #[Test]
    public function aFetchKeepsZeroAsAnExplicitTransactionBoundary(): void
    {
        $this->assertSame(0, $this->fetchRequestFor(["transaction" => 0])->transactionNumber?->intValue);
    }

    #[Test]
    public function aFetchScopedByTokenReadsAfterThatToken(): void
    {
        $this->assertNotNull($this->fetchRequestFor(["token" => new Dictionary(["store-1" => 7])])->token);
    }

    #[Test]
    public function aFetchWithoutAScopeIsRefused(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->fetchRequestFor([]);
    }

    #[Test]
    public function theDefaultResultTypeIsLeftUntouched(): void
    {
        $request = $this->fetchRequestFor(["transaction" => "1"]);
        $this->assertSame(PersistentHistoryResultType::transactionsAndChanges, $request->resultType);
    }

    /** @return iterable<string, array{string, PersistentHistoryResultType}> */
    public static function resultTypeProvider(): iterable
    {
        yield "statusOnly" => ["statusOnly", PersistentHistoryResultType::statusOnly];
        yield "count" => ["count", PersistentHistoryResultType::count];
        yield "objectIDs" => ["objectIDs", PersistentHistoryResultType::objectIDs];
        yield "transactionsOnly" => ["transactionsOnly", PersistentHistoryResultType::transactionsOnly];
        yield "changesOnly" => ["changesOnly", PersistentHistoryResultType::changesOnly];
        yield "transactionsAndChanges" => ["transactionsAndChanges", PersistentHistoryResultType::transactionsAndChanges];
    }

    #[Test]
    #[DataProvider("resultTypeProvider")]
    public function eachNamedResultTypeIsMappedOntoItsCase(string $name, PersistentHistoryResultType $expected): void
    {
        $this->assertSame($expected, $this->fetchRequestFor(["transaction" => "1", "resultType" => $name])->resultType);
    }

    #[Test]
    public function anUnknownResultTypeIsRefusedAndNamesTheAllowedOnes(): void
    {
        try {
            $this->fetchRequestFor(["transaction" => "1", "resultType" => "nope"]);
            $this->fail("An unknown resultType must be refused.");
        } catch (InternalInconsistencyException $exception) {
            $reason = (string)$exception->error->localizedFailureReason;
            $this->assertStringContainsString("nope", $reason);
            $this->assertStringContainsString("transactionsAndChanges", $reason);
        }
    }

    #[Test]
    public function aTokenMustBeAnObjectOfStoreIdentifiersToNumbers(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->purgeRequest(["token" => "not-an-object"]);
    }

    #[Test]
    public function aRequestWithoutAPredicateIsNotFiltered(): void
    {
        $this->assertNull($this->invoke("transactionFilter", new Dictionary([])));
    }

    #[Test]
    public function anEmptyPredicateDoesNotFilterEither(): void
    {
        $this->assertNull($this->invoke("transactionFilter", new Dictionary(["predicate" => ""])));
    }

    #[Test]
    public function aStatusOnlyResultIsReportedUnderTheStatusKey(): void
    {
        $shaped = $this->invoke("shapeResult", $this->historyResult(PersistentHistoryResultType::statusOnly, new Number(1)));
        $this->assertSame(1, $shaped[ServiceResponseStatusKey]->intValue);
    }

    #[Test]
    public function aCountResultIsReportedUnderTheCountKey(): void
    {
        $shaped = $this->invoke("shapeResult", $this->historyResult(PersistentHistoryResultType::count, new Number(17)));
        $this->assertSame(17, $shaped[ServiceResponseCountKey]->intValue);
    }

    #[Test]
    public function anyOtherResultIsReturnedAsItCame(): void
    {
        $payload = new ArrayClass(["a", "b"]);
        $this->assertSame($payload, $this->invoke("shapeResult", $this->historyResult(PersistentHistoryResultType::transactionsAndChanges, $payload)));
    }

    private function historyResult(PersistentHistoryResultType $resultType, ArrayClass|Number $result): PersistentHistoryResult
    {
        $instance = new ReflectionClass(PersistentHistoryResult::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(PersistentHistoryResult::class, "resultType")->setValue($instance, $resultType);
        new ReflectionProperty(PersistentHistoryResult::class, "result")->setValue($instance, $result);
        return $instance;
    }

    /** @param array<string, mixed> $arguments */
    private function purgeRequest(array $arguments): PersistentHistoryChangeRequest
    {
        /** @var PersistentHistoryChangeRequest */
        return $this->invoke("purgeRequest", new Dictionary($arguments));
    }

    /** @param array<string, mixed> $arguments */
    private function fetchRequestFor(array $arguments): PersistentHistoryChangeRequest
    {
        /** @var PersistentHistoryChangeRequest */
        return $this->invoke("fetchRequestFor", new Dictionary($arguments));
    }

    private function tool(): PersistentHistoryTool
    {
        return new PersistentHistoryTool(new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor(), new ReflectionClass(ModelDescriptor::class)->newInstanceWithoutConstructor());
    }

    private function invoke(string $method, mixed ...$arguments): mixed
    {
        return new ReflectionMethod(PersistentHistoryTool::class, $method)->invoke($this->tool(), ...$arguments);
    }
}
