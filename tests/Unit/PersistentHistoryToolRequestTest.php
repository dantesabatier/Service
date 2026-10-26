<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use Sabatier\CoreData\PersistentHistoryChangeRequest;
use Sabatier\CoreData\PersistentHistoryResult;
use Sabatier\CoreData\PersistentHistoryResultType;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Number;
use Sabatier\Service\MCP\ToolCallToPersistentHistoryChangeRequestAdapter;
use Sabatier\Service\MCP\Tools\FetchHistoryTool;
use const Sabatier\Service\ServiceResponseCountKey;
use const Sabatier\Service\ServiceResponseStatusKey;

/**
 * Fixes how the arguments of a history tool call become a `PersistentHistoryChangeRequest`, and how a fetch shapes its result. Executing the request is not exercised: that needs a store with history tracking on.
 */
final class PersistentHistoryToolRequestTest extends TestCase
{
    #[Test]
    public function aPurgeScopedByDateDeletesBeforeThatDate(): void
    {
        $request = $this->changeRequest(["beforeDate" => "2026-01-15"]);
        $this->assertTrue($request->isDelete);
        $this->assertSame("2026-01-15", substr((string)$request->date?->description, 0, 10));
    }

    #[Test]
    public function aPurgeScopedByTransactionDeletesBeforeThatNumber(): void
    {
        $request = $this->changeRequest(["beforeTransaction" => "42"]);
        $this->assertTrue($request->isDelete);
        $this->assertSame(42, $request->transactionNumber?->intValue);
    }

    #[Test]
    public function aPurgeKeepsZeroAsAnExplicitTransactionBoundary(): void
    {
        $this->assertSame(0, $this->changeRequest(["beforeTransaction" => 0])->transactionNumber?->intValue);
    }

    #[Test]
    public function aPurgeScopedByTokenDeletesBeforeThatToken(): void
    {
        $request = $this->changeRequest(["beforeToken" => new Dictionary(["store-1" => 7])]);
        $this->assertTrue($request->isDelete);
        $this->assertNotNull($request->token);
    }

    #[Test]
    public function aFetchScopedByDateReadsAfterThatDate(): void
    {
        $request = $this->changeRequest(["afterDate" => "2026-01-15"]);
        $this->assertFalse($request->isDelete);
        $this->assertSame("2026-01-15", substr((string)$request->date?->description, 0, 10));
    }

    #[Test]
    public function aFetchScopedByTransactionReadsAfterThatNumber(): void
    {
        $request = $this->changeRequest(["afterTransaction" => "42"]);
        $this->assertFalse($request->isDelete);
        $this->assertSame(42, $request->transactionNumber?->intValue);
    }

    #[Test]
    public function aFetchKeepsZeroAsAnExplicitTransactionBoundary(): void
    {
        $this->assertSame(0, $this->changeRequest(["afterTransaction" => 0])->transactionNumber?->intValue);
    }

    #[Test]
    public function aFetchScopedByTokenReadsAfterThatToken(): void
    {
        $request = $this->changeRequest(["afterToken" => new Dictionary(["store-1" => 7])]);
        $this->assertFalse($request->isDelete);
        $this->assertNotNull($request->token);
    }

    /**
     * A purge boundary names the direction it purges in, so a call carrying none is a fetch with no scope rather than a purge with one.
     */
    #[Test]
    public function aCallWithoutAScopeIsRefused(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->changeRequest([]);
    }

    #[Test]
    public function theDefaultResultTypeIsLeftUntouched(): void
    {
        $this->assertSame(PersistentHistoryResultType::transactionsAndChanges, $this->changeRequest(["afterTransaction" => 1])->resultType);
    }

    #[Test]
    #[DataProvider("resultTypeProvider")]
    public function eachResultTypeIsMappedOntoItsCase(int $rawValue, PersistentHistoryResultType $expected): void
    {
        $this->assertSame($expected, $this->changeRequest(["afterTransaction" => 1, "resultType" => $rawValue])->resultType);
    }

    /** @return iterable<string, array{int, PersistentHistoryResultType}> */
    public static function resultTypeProvider(): iterable
    {
        foreach (PersistentHistoryResultType::cases() as $case) {
            yield $case->name => [$case->value, $case];
        }
    }

    #[Test]
    public function anUnknownResultTypeIsRefused(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->changeRequest(["afterTransaction" => 1, "resultType" => 99]);
    }

    #[Test]
    public function aTokenMustBeAnObjectOfStoreIdentifiersToNumbers(): void
    {
        $this->expectException(InternalInconsistencyException::class);
        $this->changeRequest(["afterToken" => "not-an-object"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aStatusOnlyResultIsReportedUnderTheStatusKey(): void
    {
        $shaped = $this->shape($this->historyResult(PersistentHistoryResultType::statusOnly, new Number(1)));
        $this->assertSame(1, $shaped[ServiceResponseStatusKey]->intValue);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aCountResultIsReportedUnderTheCountKey(): void
    {
        $shaped = $this->shape($this->historyResult(PersistentHistoryResultType::count, new Number(17)));
        $this->assertSame(17, $shaped[ServiceResponseCountKey]->intValue);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anyOtherResultIsReturnedAsItCame(): void
    {
        $payload = new ArrayClass(["a", "b"]);
        $this->assertSame($payload, $this->shape($this->historyResult(PersistentHistoryResultType::transactionsAndChanges, $payload)));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function changeRequest(array $arguments): PersistentHistoryChangeRequest
    {
        return new ToolCallToPersistentHistoryChangeRequestAdapter(new Dictionary($arguments))->changeRequest;
    }

    /**
     * @throws ReflectionException
     */
    private function shape(PersistentHistoryResult $result): mixed
    {
        $tool = new ReflectionClass(FetchHistoryTool::class)->newInstanceWithoutConstructor();
        return new ReflectionMethod(FetchHistoryTool::class, "shape")->invoke($tool, $result);
    }

    /** @throws ReflectionException */
    private function historyResult(PersistentHistoryResultType $resultType, ArrayClass|Number $result): PersistentHistoryResult
    {
        $instance = new ReflectionClass(PersistentHistoryResult::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(PersistentHistoryResult::class, "resultType")->setValue($instance, $resultType);
        new ReflectionProperty(PersistentHistoryResult::class, "result")->setValue($instance, $result);
        return $instance;
    }
}
