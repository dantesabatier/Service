<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\UUID;
use Sabatier\Service\MCP\InitializeHandler;
use Sabatier\Service\MCP\JSONRPCError;
use Sabatier\Service\MCP\JSONRPCRequestParser;
use Sabatier\Service\MCP\MCPRequestHandler;
use Sabatier\Service\MCP\MethodDispatcher;
use Sabatier\Service\MCP\ToolsCallHandler;
use Sabatier\Service\MCP\ToolsListHandler;
use Sabatier\Service\Request;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;
use const Sabatier\Service\MCP\JSONRPCErrorCodeInternalError;
use const Sabatier\Service\MCP\JSONRPCErrorCodeInvalidRequest;
use const Sabatier\Service\MCP\JSONRPCErrorCodeParseErrorCode;

/**
 * Covers the seam between parsing a JSON-RPC request and dispatching it, including the guarantee
 * that nothing thrown downstream escapes as anything but a JSON-RPC error.
 */
final class MCPRequestHandlerTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = $_SERVER;
        ObjectClass::$staticAssociatedValues = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        ObjectClass::$staticAssociatedValues = [];
        parent::tearDown();
    }

    /** @throws Exception */
    #[Test]
    public function aNotificationIsAcknowledgedWithNothing(): void
    {
        $this->assertNull($this->handle(["jsonrpc" => "2.0", "method" => "notifications/initialized"]));
    }

    /** @throws Exception */
    #[Test]
    public function aPingIsAnsweredWithNothing(): void
    {
        $this->assertNull($this->handle(["jsonrpc" => "2.0", "id" => 1, "method" => "ping"]));
    }

    /** @throws Exception */
    #[Test]
    public function anUnknownMethodComesBackAsAJSONRPCError(): void
    {
        $this->assertInstanceOf(JSONRPCError::class, $this->handle(["jsonrpc" => "2.0", "id" => 1, "method" => "tools/destroy"]));
    }

    /** @throws Exception */
    #[Test]
    public function aRequestCarryingNothingIsAParseError(): void
    {
        // The parser's own error is returned as it stands; dispatching it instead would answer with
        // "method not found" and lose what actually went wrong.
        /** @var JSONRPCError $result */
        $result = $this->handle([]);
        $this->assertInstanceOf(JSONRPCError::class, $result);
        $this->assertSame(JSONRPCErrorCodeParseErrorCode, $result->code);
    }

    /** @throws Exception */
    #[Test]
    public function aRequestWithoutAMethodIsRefusedAsAnInvalidRequest(): void
    {
        /** @var JSONRPCError $result */
        $result = $this->handle(["jsonrpc" => "2.0", "id" => 1]);
        $this->assertInstanceOf(JSONRPCError::class, $result);
        $this->assertSame(JSONRPCErrorCodeInvalidRequest, $result->code);
    }

    /** @throws Exception */
    #[Test]
    public function aFailureBelowTheHandlerIsReportedAsAnInternalError(): void
    {
        // The handler owns the last catch: a handler blowing up must not escape as an HTTP 500.
        $result = $this->handle(["jsonrpc" => "2.0", "id" => 1, "method" => "tools/list"]);
        $this->assertInstanceOf(JSONRPCError::class, $result);
        $this->assertSame(JSONRPCErrorCodeInternalError, $result->code);
    }

    /** @throws Exception */
    #[Test]
    public function theInternalErrorCarriesTheReasonItFailedFor(): void
    {
        /** @var JSONRPCError $result */
        $result = $this->handle(["jsonrpc" => "2.0", "id" => 1, "method" => "tools/list"]);
        $this->assertNotSame("", (string)$result->userInfo[LocalizedFailureReasonErrorKey]);
    }

    /**
     * The handler logs whatever it catches, so the destination is redirected for the call and the
     * line discarded: it is the framework reporting, not something the suite needs to show.
     *
     * @param array<string, mixed> $parameters
     * @throws Exception
     */
    private function handle(array $parameters): mixed
    {
        $destination = ini_get("error_log");
        $url = FileManager::default()->temporaryDirectory->appendingPathComponent(new UUID()->uuidString);
        ini_set("error_log", $url->path);
        try {
            return $this->dispatch($parameters);
        } finally {
            ini_set("error_log", $destination === false ? "" : $destination);
            FileManager::default()->removeItem($url);
        }
    }

    /**
     * @param array<string, mixed> $parameters
     * @throws ReflectionException
     */
    private function dispatch(array $parameters): mixed
    {
        $dispatcher = new MethodDispatcher(
            new ReflectionClass(InitializeHandler::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(ToolsListHandler::class)->newInstanceWithoutConstructor(),
            new ReflectionClass(ToolsCallHandler::class)->newInstanceWithoutConstructor()
        );
        return new MCPRequestHandler(new JSONRPCRequestParser(), $dispatcher)->handle($this->request($parameters));
    }

    /**
     * @param array<string, mixed> $parameters
     * @throws ReflectionException
     */
    private function request(array $parameters): Request
    {
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["REQUEST_URI"] = "/mcp";
        $_SERVER["REQUEST_METHOD"] = HTTPRequestMethod::post;
        $request = new ReflectionClass(Request::class)->newInstanceWithoutConstructor();
        $request->httpMethod = HTTPRequestMethod::post;
        new ReflectionProperty(Request::class, "parameters")->setValue($request, new Dictionary($parameters));
        return $request;
    }
}
