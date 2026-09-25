<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\InitializeHandler;
use Sabatier\Service\MCP\JSONRPCError;
use Sabatier\Service\MCP\MethodDispatcher;
use Sabatier\Service\MCP\RPCMessage;
use Sabatier\Service\MCP\ToolsCallHandler;
use Sabatier\Service\MCP\ToolsListHandler;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;
use const Sabatier\Service\MCP\JSONRPCErrorCodeInvalidParams;

/**
 * Fixes which handler each JSON-RPC method reaches, and how an unknown one is answered. The
 * handlers themselves are not exercised here: they need the shared application behind them.
 */
final class MethodDispatcherTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function acknowledgedMethodProvider(): iterable
    {
        yield "ping" => ["ping"];
        yield "the initialized notification" => ["notifications/initialized"];
    }

    /** @throws ReflectionException */
    #[Test]
    #[DataProvider("acknowledgedMethodProvider")]
    public function aMethodThatOnlyNeedsAcknowledgingAnswersWithNothing(string $method): void
    {
        $this->assertNull($this->dispatcher()->dispatch($this->message($method)));
    }

    /** @throws ReflectionException */
    #[Test]
    public function anUnknownMethodIsAnsweredWithAJSONRPCError(): void
    {
        $result = $this->dispatcher()->dispatch($this->message("tools/destroy"));
        $this->assertInstanceOf(JSONRPCError::class, $result);
        $this->assertSame(JSONRPCErrorCodeInvalidParams, $result->code);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theErrorNamesTheMethodThatWasNotFound(): void
    {
        /** @var JSONRPCError $result */
        $result = $this->dispatcher()->dispatch($this->message("tools/destroy"));
        $this->assertStringContainsString("tools/destroy", (string)$result->userInfo[LocalizedFailureReasonErrorKey]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anEmptyMethodIsNotMistakenForAKnownOne(): void
    {
        $this->assertInstanceOf(JSONRPCError::class, $this->dispatcher()->dispatch($this->message("")));
    }

    /** @throws ReflectionException */
    #[Test]
    public function theMethodIsMatchedExactly(): void
    {
        $this->assertInstanceOf(JSONRPCError::class, $this->dispatcher()->dispatch($this->message("Ping")));
        $this->assertInstanceOf(JSONRPCError::class, $this->dispatcher()->dispatch($this->message("ping ")));
    }

    private function message(string $method): RPCMessage
    {
        return new RPCMessage(1, $method, new Dictionary());
    }

    /** @throws ReflectionException */
    private function dispatcher(): MethodDispatcher
    {
        return new MethodDispatcher(
            new ReflectionClass(InitializeHandler::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(ToolsListHandler::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(ToolsCallHandler::class)->newInstanceWithoutConstructor()
        );
    }
}
