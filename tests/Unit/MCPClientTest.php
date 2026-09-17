<?php

// PHPUnit intentionally owns the exception boundary for this test file.
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use InvalidArgumentException;
use JsonException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\URL;
use Sabatier\Service\LLM\LLMClock;
use Sabatier\Service\LLM\LLMAgent;
use Sabatier\Service\LLM\LLMClient;
use Sabatier\Service\LLM\LLMExecutionDeadline;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;
use Sabatier\Service\LLM\LLMRunStopReason;
use Sabatier\Service\LLM\LLMTurn;
use Sabatier\Service\LLM\LLMToolCall;
use Sabatier\Service\LLM\LLMToolProviderException;
use Sabatier\Service\LLM\MCPToolExecutor;
use Sabatier\Service\MCP\MCPClient;
use Sabatier\Service\MCP\MCPClientException;
use Sabatier\Service\MCP\MCPClientResponse;
use Sabatier\Service\MCP\MCPTransport;
use Sabatier\Service\MCP\StreamableHTTPMCPTransport;
use stdClass;
use const Sabatier\Service\MCPProtocolVersionHeader;
use const Sabatier\Service\MCPSessionHeader;

final class MCPClientTest extends TestCase
{
    #[Test]
    public function catalogueInitializationNegotiatesAndReusesOneSession(): void
    {
        $transport = new RecordingMCPTransport(new ArrayClass([
            $this->response(1, ["protocolVersion" => "2025-11-25"], new Dictionary([MCPSessionHeader => "session-1"])),
            new MCPClientResponse(202),
            $this->response(2, ["tools" => [["name" => "search", "description" => "Search records.", "title" => "Search", "inputSchema" => ["type" => "object", "properties" => ["query" => ["type" => "string"]]]]]]),
        ]));
        $client = new MCPClient($transport, catalogTimeout: 12.0);

        $tools = $client->tools;
        $secondRead = $client->tools;

        $this->assertSame($tools, $secondRead);
        $this->assertSame("session-1", $client->sessionIdentifier);
        $this->assertSame("2025-11-25", $client->protocolVersion);
        $this->assertSame(1, $tools->count);
        $this->assertSame("search", $tools[0]->name);
        $this->assertSame("Search records.", $tools[0]->description);
        $this->assertSame("Search", $tools[0]->title);
        $this->assertNull($tools[0]->annotations);
        $this->assertIsArray($tools[0]->inputSchema["properties"]);
        $this->assertIsArray($tools[0]->inputSchema["properties"]["query"]);
        $this->assertSame("string", $tools[0]->inputSchema["properties"]["query"]["type"]);
        $this->assertSame(3, $transport->messages->count);
        $this->assertSame("initialize", $transport->messages[0]["method"]);
        $this->assertSame("notifications/initialized", $transport->messages[1]["method"]);
        $this->assertSame("tools/list", $transport->messages[2]["method"]);
        $this->assertNull($transport->headers[0][MCPSessionHeader]);
        $this->assertSame("session-1", $transport->headers[1][MCPSessionHeader]);
        $this->assertSame("2025-11-25", $transport->headers[2][MCPProtocolVersionHeader]);
        $this->assertSame([12.0, 12.0, 12.0], $transport->timeouts->array);
    }

    #[Test]
    public function toolCallReturnsJoinedTextAndTheRemoteErrorFlag(): void
    {
        $transport = new RecordingMCPTransport(new ArrayClass([
            $this->response(1, ["protocolVersion" => "2025-11-25"], new Dictionary([MCPSessionHeader => "session-2"])),
            new MCPClientResponse(202),
            $this->response(2, ["content" => [["type" => "text", "text" => "first"], ["type" => "text", "text" => "second"]], "isError" => true]),
        ]));
        $client = new MCPClient($transport);

        $result = $client->callTool("search", new Dictionary(["query" => "term"]), 2.5);

        $this->assertSame("first\nsecond", $result->text);
        $this->assertTrue($result->isError);
        $this->assertSame("tools/call", $transport->messages[2]["method"]);
        $this->assertSame("search", $transport->messages[2]["params"]["name"]);
        $this->assertSame("term", $transport->messages[2]["params"]["arguments"]["query"]);
        $this->assertSame(2.5, $transport->timeouts[2]);
    }

    #[Test]
    public function remoteExecutorPreservesCorrectableToolFailureForTheAgent(): void
    {
        $transport = new RecordingMCPTransport(new ArrayClass([
            $this->response(1, ["protocolVersion" => "2025-11-25"], new Dictionary([MCPSessionHeader => "session-correctable"])),
            new MCPClientResponse(202),
            $this->response(2, ["content" => [["type" => "text", "text" => "query must not be empty"]], "isError" => true]),
        ]));
        $executor = new MCPToolExecutor(new MCPClient($transport));

        $result = $executor->execute(new LLMToolCall("call-correctable", "search", new Dictionary()));

        $this->assertSame("query must not be empty", $result->text);
        $this->assertTrue($result->isError);
    }

    #[Test]
    public function expiredSessionIsClearedAndReportedAsTransient(): void
    {
        $transport = new RecordingMCPTransport(new ArrayClass([
            $this->response(1, ["protocolVersion" => "2025-11-25"], new Dictionary([MCPSessionHeader => "expired"])),
            new MCPClientResponse(202),
            new MCPClientResponse(404, body: "session expired"),
        ]));
        $client = new MCPClient($transport);

        try {
            $client->callTool("search", new Dictionary());
            $this->fail("The expired session must fail the call.");
        } catch (MCPClientException $exception) {
            $this->assertTrue($exception->isTransient);
            $this->assertStringContainsString("HTTP 404", $exception->getMessage());
        }
        $this->assertNull($client->sessionIdentifier);
        $this->assertNull($client->protocolVersion);
    }

    #[Test]
    public function malformedRemoteContentIsAProtocolFailure(): void
    {
        $transport = new RecordingMCPTransport(new ArrayClass([
            $this->response(1, ["protocolVersion" => "2025-11-25"], new Dictionary([MCPSessionHeader => "session-3"])),
            new MCPClientResponse(202),
            $this->response(2, ["content" => [["type" => "image", "data" => "..."]]]),
        ]));
        $client = new MCPClient($transport);

        $this->expectException(MCPClientException::class);
        $this->expectExceptionMessage("unsupported tool content");
        $client->callTool("render", new Dictionary());
    }

    #[Test]
    public function catalogueTimeoutMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MCPClient(new RecordingMCPTransport(new ArrayClass()), catalogTimeout: 0.0);
    }

    #[Test]
    public function remoteExecutorIsConservativeUntilTrustedClassifiersOptIn(): void
    {
        $annotations = ["title" => "Search", "readOnlyHint" => true, "destructiveHint" => false, "idempotentHint" => true, "openWorldHint" => false];
        $client = $this->catalogueClient($annotations);
        $call = new LLMToolCall("call-1", "search", new Dictionary(["query" => "term"]));
        $conservative = new MCPToolExecutor($client);

        $this->assertTrue($conservative->contains($call));
        $descriptor = $conservative->tools->first;
        $this->assertNotNull($descriptor);
        $this->assertSame($annotations, $descriptor->annotations);
        $this->assertFalse($conservative->isReadOnly($call));
        $this->assertFalse($conservative->isCacheable($call));

        $trusted = new MCPToolExecutor($client, fn(LLMToolCall $candidate): bool => $candidate->name === "search", fn(LLMToolCall $candidate): bool => $candidate->arguments["query"] === "term");

        $this->assertTrue($trusted->isReadOnly($call));
        $this->assertTrue($trusted->isCacheable($call));
    }

    #[Test]
    public function remoteReadOnlyHintDoesNotBypassAgentWriteApproval(): void
    {
        $executor = new MCPToolExecutor($this->catalogueClient(["readOnlyHint" => true, "idempotentHint" => true]));
        $call = new LLMToolCall("remote-call", "search", new Dictionary());
        $model = $this->createMock(LLMClient::class);
        $model->expects($this->once())->method("complete")->willReturn(new LLMTurn(null, new ArrayClass([$call])));

        $run = new LLMAgent($model, $executor, canSpawnSubagents: false)->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "Search records.")]));

        $this->assertSame(LLMRunStopReason::writeApprovalRequired, $run->stopReason);
    }

    #[Test]
    public function remoteEmptyAndPartialAnnotationsAreNotFilledWithDefaults(): void
    {
        foreach ([[], ["openWorldHint" => false], ["title" => "Legacy title"]] as $annotations) {
            $descriptor = $this->catalogueClient($annotations)->tools->first;
            $this->assertNotNull($descriptor);
            $this->assertSame($annotations, $descriptor->annotations);
        }
    }

    /**
     * @param mixed $annotations The malformed wire value to reject without coercion.
     * @throws JsonException
     * @throws MCPClientException
     */
    #[Test]
    #[DataProvider("invalidAnnotations")]
    public function malformedAnnotationValuesAreProtocolFailures(mixed $annotations): void
    {
        $this->expectException(MCPClientException::class);
        $this->expectExceptionMessage("annotation");

        $this->catalogueClient($annotations)->tools;
    }

    /** @return array<string, array{mixed}> */
    public static function invalidAnnotations(): array
    {
        return [
            "scalar" => [true],
            "list" => [[true]],
            "readOnly string" => [["readOnlyHint" => "false"]],
            "destructive number" => [["destructiveHint" => 0]],
            "idempotent null" => [["idempotentHint" => null]],
            "openWorld string" => [["openWorldHint" => "true"]],
            "title boolean" => [["title" => false]],
        ];
    }

    #[Test]
    public function remoteExecutorPassesTheRemainingDeadlineToTheToolCall(): void
    {
        $transport = new RecordingMCPTransport(new ArrayClass([
            $this->response(1, ["protocolVersion" => "2025-11-25"], new Dictionary([MCPSessionHeader => "session-6"])),
            new MCPClientResponse(202),
            $this->response(2, ["content" => [["type" => "text", "text" => "remote result"]]]),
        ]));
        $clock = new MutableMCPClock();
        $deadline = new LLMExecutionDeadline($clock, 5.0);
        $executor = new MCPToolExecutor(new MCPClient($transport));

        $result = $executor->execute(new LLMToolCall("call-2", "search", new Dictionary()), $deadline);

        $this->assertSame("remote result", $result->text);
        $this->assertFalse($result->isError);
        $this->assertSame(5.0, $transport->timeouts[2]);
    }

    #[Test]
    public function remoteExecutorNormalizesTransportFailureForTheAgentLoop(): void
    {
        $transportError = new Error("MCPTransportTestDomain", 1);
        $clientFailure = new MCPClientException("tool service unavailable", true, transportError: $transportError);
        $transport = new RecordingMCPTransport(new ArrayClass([
            $this->response(1, ["protocolVersion" => "2025-11-25"], new Dictionary([MCPSessionHeader => "session-7"])),
            new MCPClientResponse(202),
            $clientFailure,
        ]));
        $executor = new MCPToolExecutor(new MCPClient($transport));

        try {
            $executor->execute(new LLMToolCall("call-3", "search", new Dictionary()));
            $this->fail("The executor must normalize the client failure.");
        } catch (LLMToolProviderException $exception) {
            $this->assertSame(HTTPStatusCode::internalServerError, $exception->getCode());
            $this->assertSame("tool service unavailable", $exception->error->localizedFailureReason);
            $this->assertSame($clientFailure, $exception->providerError);
            $this->assertSame($transportError, $clientFailure->transportError);
            $this->assertTrue($exception->isTransient);
            $this->assertStringContainsString("tool service unavailable", $exception->getMessage());
        }
    }

    #[Test]
    public function httpTransportNormalizesMessageEncodingFailure(): void
    {
        $message = new Dictionary();
        $message["self"] = $message;
        $transport = new StreamableHTTPMCPTransport(new URL("https://example.test/mcp"));

        try {
            $transport->send($message, new Dictionary());
            $this->fail("The transport must normalize JSON encoding failures.");
        } catch (MCPClientException $exception) {
            $this->assertFalse($exception->isTransient);
            $this->assertStringContainsString("could not be encoded", $exception->getMessage());
        }
    }

    /** @return iterable<string, array{float}> */
    public static function nonPositiveTimeoutProvider(): iterable
    {
        yield "zero" => [0.0];
        yield "negative" => [-1.0];
    }

    #[Test]
    #[DataProvider("nonPositiveTimeoutProvider")]
    public function theHttpTransportRefusesATimeoutThatCannotElapse(float $timeout): void
    {
        $this->expectException(InvalidArgumentException::class);
        new StreamableHTTPMCPTransport(new URL("https://example.test/mcp"), timeoutInterval: $timeout);
    }

    #[Test]
    public function theHttpTransportKeepsItsOwnCopyOfTheHeadersItWasGiven(): void
    {
        $headers = new Dictionary(["Authorization" => "Bearer one"]);
        $transport = new StreamableHTTPMCPTransport(new URL("https://example.test/mcp"), $headers);
        $headers["Authorization"] = "Bearer two";
        /** @var Dictionary<string> $retained */
        $retained = new ReflectionProperty(StreamableHTTPMCPTransport::class, "additionalHeaders")->getValue($transport);
        $this->assertSame("Bearer one", $retained["Authorization"]);
    }

    /**
     * @param mixed $annotations
     * @return MCPClient
     * @throws JsonException
     */
    private function catalogueClient(mixed $annotations): MCPClient
    {
        return new MCPClient(new RecordingMCPTransport(new ArrayClass([
            $this->response(1, ["protocolVersion" => "2025-11-25"], new Dictionary([MCPSessionHeader => "annotations-session"])),
            new MCPClientResponse(202),
            $this->response(2, ["tools" => [["name" => "search", "inputSchema" => ["type" => "object"], "annotations" => $annotations === [] ? new stdClass() : $annotations]]]),
        ])));
    }

    #[Test]
    public function anEmptyBodyIsRefusedAndWorthRetrying(): void
    {
        $exception = $this->failureFor(new MCPClientResponse(200, new Dictionary(), "   "));
        $this->assertStringContainsString("empty JSON-RPC response", $exception->getMessage());
        $this->assertTrue($exception->isTransient);
    }

    #[Test]
    public function malformedJSONIsRefusedAndWorthRetrying(): void
    {
        $exception = $this->failureFor(new MCPClientResponse(200, new Dictionary(), "{not json"));
        $this->assertStringContainsString("malformed JSON", $exception->getMessage());
        $this->assertTrue($exception->isTransient);
    }

    /** @return iterable<string, array{string, string}> */
    public static function malformedEnvelopeProvider(): iterable
    {
        yield "a JSON array instead of an object" => ["[1, 2]", "not an object"];
        yield "a JSON scalar" => ["17", "not an object"];
        yield "a foreign protocol version" => ["{\"jsonrpc\": \"1.0\", \"id\": 1, \"result\": {}}", "mismatched"];
        yield "an answer to another request" => ["{\"jsonrpc\": \"2.0\", \"id\": 99, \"result\": {}}", "mismatched"];
        yield "no identifier at all" => ["{\"jsonrpc\": \"2.0\", \"result\": {}}", "mismatched"];
        yield "a result that is not an object" => ["{\"jsonrpc\": \"2.0\", \"id\": 1, \"result\": [1, 2]}", "without an object result"];
        yield "no result and no error" => ["{\"jsonrpc\": \"2.0\", \"id\": 1}", "without an object result"];
        yield "an error that is not an object" => ["{\"jsonrpc\": \"2.0\", \"id\": 1, \"error\": [1]}", "invalid JSON-RPC error object"];
    }

    #[Test]
    #[DataProvider("malformedEnvelopeProvider")]
    public function aMalformedEnvelopeIsRefusedWithoutRetrying(string $body, string $expected): void
    {
        $exception = $this->failureFor(new MCPClientResponse(200, new Dictionary(), $body));
        $this->assertStringContainsString($expected, $exception->getMessage());
        $this->assertFalse($exception->isTransient);
    }

    #[Test]
    public function aRemoteErrorIsReportedWithItsOwnMessage(): void
    {
        $exception = $this->failureFor(new MCPClientResponse(200, new Dictionary(), "{\"jsonrpc\": \"2.0\", \"id\": 1, \"error\": {\"code\": -32601, \"message\": \"Method not found\"}}"));
        $this->assertSame("Method not found", $exception->getMessage());
        $this->assertFalse($exception->isTransient);
    }

    #[Test]
    public function aRemoteErrorWithoutAMessageStillReadsAsOne(): void
    {
        $exception = $this->failureFor(new MCPClientResponse(200, new Dictionary(), "{\"jsonrpc\": \"2.0\", \"id\": 1, \"error\": {\"code\": -32601}}"));
        $this->assertStringContainsString("returned a JSON-RPC error", $exception->getMessage());
    }

    /** The handshake is the first exchange, so a response to it is what the client validates. */
    private function failureFor(MCPClientResponse $response): MCPClientException
    {
        $client = new MCPClient(new RecordingMCPTransport(new ArrayClass([$response])));
        try {
            $client->tools;
        } catch (MCPClientException $exception) {
            return $exception;
        }
        $this->fail("The client must refuse this response.");
    }

    /**
     * @param int $identifier
     * @param array<string, mixed> $result
     * @param Dictionary<mixed> $headers
     * @return MCPClientResponse
     * @throws JsonException
     */
    private function response(int $identifier, array $result, Dictionary $headers = new Dictionary()): MCPClientResponse
    {
        return new MCPClientResponse(200, $headers, json_encode(["jsonrpc" => "2.0", "id" => $identifier, "result" => $result], JSON_THROW_ON_ERROR));
    }
}

final class MutableMCPClock implements LLMClock
{
    public float $time = 0.0;

    #[Override]
    public float $timestamp {
        get => $this->time;
    }
    #[Override]
    public float $monotonicTime {
        get => $this->time;
    }
}

final class RecordingMCPTransport implements MCPTransport
{
    /** @var ArrayClass<Dictionary<mixed>> */
    public readonly ArrayClass $messages;
    /** @var ArrayClass<Dictionary<string>> */
    public readonly ArrayClass $headers;
    /** @var ArrayClass<float|null> */
    public readonly ArrayClass $timeouts;

    /** @param ArrayClass<MCPClientResponse|MCPClientException> $responses Responses or failures returned in request order. */
    public function __construct(private readonly ArrayClass $responses)
    {
        $this->messages = new ArrayClass();
        $this->headers = new ArrayClass();
        $this->timeouts = new ArrayClass();
    }

    #[Override]
    public function send(Dictionary $message, Dictionary $headers, ?float $timeout = null): MCPClientResponse
    {
        $this->messages->append(clone $message);
        $this->headers->append(clone $headers);
        $this->timeouts->append($timeout);
        $response = $this->responses->removeAt(0);
        return $response instanceof MCPClientException ? throw $response : $response;
    }
}
