<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\JSONRPCError;
use Sabatier\Service\MCP\JSONRPCRequestParser;
use const Sabatier\Service\MCP\JSONRPCErrorCodeInvalidRequest;
use const Sabatier\Service\MCP\JSONRPCErrorCodeParseErrorCode;
use Sabatier\Service\MCP\RPCMessage;
use Sabatier\Service\Request;

final class JSONRPCRequestParserTest extends TestCase
{
    /** @throws ReflectionException */
    private function makeRequest(array $data): Request
    {
        $request = new ReflectionClass(Request::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(Request::class, "parameters")->setValue($request, new Dictionary($data));
        return $request;
    }

    private function parser(): JSONRPCRequestParser
    {
        return new JSONRPCRequestParser();
    }

    // --- Empty / missing data ---

    /** @throws ReflectionException */
    #[Test]
    public function returnsParseErrorWhenParametersAreEmpty(): void
    {
        $result = $this->parser()->parse($this->makeRequest([]));
        $this->assertInstanceOf(JSONRPCError::class, $result);
        $this->assertSame(JSONRPCErrorCodeParseErrorCode, $result->code);
    }

    /** @throws ReflectionException */
    #[Test]
    public function returnsParseErrorWhenOnlyNonDictionaryParamsPresent(): void
    {
        // pruner removes params=string, leaving only that key; result is empty after prune
        $result = $this->parser()->parse($this->makeRequest(["params" => "bad"]));
        $this->assertInstanceOf(JSONRPCError::class, $result);
        $this->assertSame(JSONRPCErrorCodeParseErrorCode, $result->code);
    }

    /** @throws ReflectionException */
    #[Test]
    public function returnsInvalidRequestWhenMethodIsMissing(): void
    {
        $result = $this->parser()->parse($this->makeRequest(["id" => 1]));
        $this->assertInstanceOf(JSONRPCError::class, $result);
        $this->assertSame(JSONRPCErrorCodeInvalidRequest, $result->code);
    }

    // --- Notification (no id) ---

    /** @throws ReflectionException */
    #[Test]
    public function returnsNullForNotificationWithNoId(): void
    {
        $result = $this->parser()->parse($this->makeRequest(["method" => "notifications/initialized"]));
        $this->assertNull($result);
    }

    // --- Valid request ---

    /** @throws ReflectionException */
    #[Test]
    public function returnsRPCMessageForValidRequest(): void
    {
        $result = $this->parser()->parse($this->makeRequest([
            "id" => 1,
            "method" => "tools/list",
        ]));
        $this->assertInstanceOf(RPCMessage::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame("tools/list", $result->method);
    }

    /** @throws ReflectionException */
    #[Test]
    public function paramsDefaultToEmptyDictionaryWhenAbsent(): void
    {
        $result = $this->parser()->parse($this->makeRequest(["id" => 1, "method" => "tools/list"]));
        $this->assertInstanceOf(RPCMessage::class, $result);
        $this->assertTrue($result->params->isEmpty);
    }

    /** @throws ReflectionException */
    #[Test]
    public function dictionaryParamsAreIncludedInRPCMessage(): void
    {
        $result = $this->parser()->parse($this->makeRequest([
            "id" => 2,
            "method" => "tools/call",
            "params" => new Dictionary(["name" => "fetch", "arguments" => new Dictionary(["url" => "https://example.com"])]),
        ]));
        $this->assertInstanceOf(RPCMessage::class, $result);
        $this->assertSame("fetch", $result->params["name"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function stringParamsArePrunedBeforePackagingIntoRPCMessage(): void
    {
        // params is a string → pruner removes it → params defaults to empty Dictionary
        $result = $this->parser()->parse($this->makeRequest([
            "id" => 3,
            "method" => "tools/call",
            "params" => "invalid",
        ]));
        $this->assertInstanceOf(RPCMessage::class, $result);
        $this->assertTrue($result->params->isEmpty);
    }

    /** @throws ReflectionException */
    #[Test]
    public function idCanBeAString(): void
    {
        $result = $this->parser()->parse($this->makeRequest(["id" => "req-abc", "method" => "ping"]));
        $this->assertInstanceOf(RPCMessage::class, $result);
        $this->assertSame("req-abc", $result->id);
    }

    /** @throws ReflectionException */
    #[Test]
    public function extraFieldsAreIgnored(): void
    {
        $result = $this->parser()->parse($this->makeRequest([
            "jsonrpc" => "2.0",
            "id" => 5,
            "method" => "initialize",
            "extra" => "ignored",
        ]));
        $this->assertInstanceOf(RPCMessage::class, $result);
        $this->assertSame("initialize", $result->method);
    }
}
