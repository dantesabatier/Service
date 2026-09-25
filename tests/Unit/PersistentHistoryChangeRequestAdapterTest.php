<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Service\BadRequestException;
use Sabatier\Service\PersistentHistoryChangeRequestAdapter;
use Sabatier\Service\Request;
use const Sabatier\Service\PersistentHistoryAfterTokenKey;
use const Sabatier\Service\PersistentHistoryAfterTransactionKey;
use const Sabatier\Service\PersistentHistoryBeforeTokenKey;
use const Sabatier\Service\PersistentHistoryBeforeTransactionKey;

final class PersistentHistoryChangeRequestAdapterTest extends TestCase
{
    /** @return iterable<string, array{string, array<string, mixed>}> */
    public static function missingScopeProvider(): iterable
    {
        yield "GET without a scope" => [HTTPRequestMethod::get, []];
        yield "GET with an empty token" => [HTTPRequestMethod::get, [PersistentHistoryAfterTokenKey => ""]];
        yield "GET with a whitespace token" => [HTTPRequestMethod::get, [PersistentHistoryAfterTokenKey => "  "]];
        yield "DELETE without a scope" => [HTTPRequestMethod::delete, []];
        yield "DELETE with an empty token" => [HTTPRequestMethod::delete, [PersistentHistoryBeforeTokenKey => ""]];
        yield "DELETE with a whitespace token" => [HTTPRequestMethod::delete, [PersistentHistoryBeforeTokenKey => "  "]];
    }

    #[Test]
    #[DataProvider("missingScopeProvider")]
    public function rejectsMissingOrEmptyScope(string $method, array $parameters): void
    {
        $this->expectException(BadRequestException::class);
        new PersistentHistoryChangeRequestAdapter($this->request($method, $parameters))->changeRequest;
    }

    #[Test]
    public function preservesZeroAsAnExplicitFetchTransactionBoundary(): void
    {
        $request = new PersistentHistoryChangeRequestAdapter($this->request(HTTPRequestMethod::get, [PersistentHistoryAfterTransactionKey => "0"]))->changeRequest;
        $this->assertSame(0, $request->transactionNumber?->intValue);
        $this->assertFalse($request->isDelete);
    }

    #[Test]
    public function preservesZeroAsAnExplicitDeleteTransactionBoundary(): void
    {
        $request = new PersistentHistoryChangeRequestAdapter($this->request(HTTPRequestMethod::delete, [PersistentHistoryBeforeTransactionKey => "0"]))->changeRequest;
        $this->assertSame(0, $request->transactionNumber?->intValue);
        $this->assertTrue($request->isDelete);
    }

    /** @param array<string, mixed> $parameters */
    private function request(string $method, array $parameters): Request
    {
        $request = new ReflectionClass(Request::class)->newInstanceWithoutConstructor();
        $request->httpMethod = $method;
        new ReflectionProperty(Request::class, "parameters")->setValue($request, new Dictionary($parameters));
        return $request;
    }
}
