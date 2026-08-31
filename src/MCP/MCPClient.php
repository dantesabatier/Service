<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

use InvalidArgumentException;
use JsonException;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Response\ToolCallResult;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use stdClass;
use function Sabatier\Foundation\in_range;
use const Sabatier\Service\MCPProtocolVersionHeader;
use const Sabatier\Service\MCPProtocolVersionLatestStable;
use const Sabatier\Service\MCPSessionHeader;

/** Maintains one stateful MCP session, normalizing its remote catalogue and tool results for framework callers. */
final class MCPClient
{
    /** @var ArrayClass<ToolDescriptor>|null */
    private ?ArrayClass $cachedTools = null;
    private int $nextIdentifier = 1;
    /** @var string|null The session identifier issued by the remote server. */
    private(set) ?string $sessionIdentifier = null;
    /** @var string|null The protocol version negotiated with the remote server. */
    private(set) ?string $protocolVersion = null;
    /** @var ArrayClass<ToolDescriptor> The remote catalogue, initialized and fetched once on first access. */
    public ArrayClass $tools {
        get => $this->cachedTools ??= $this->loadTools();
    }

    /**
     * @param MCPTransport $transport The mechanism that sends JSON-RPC messages.
     * @param string $clientName The client name reported during initialization.
     * @param string $clientVersion The client version reported during initialization.
     * @param string $requestedProtocolVersion The MCP protocol version requested during initialization.
     * @param float $catalogTimeout Seconds available to initialize and fetch the catalogue per exchange.
     */
    public function __construct(private readonly MCPTransport $transport, private readonly string $clientName = "Sabatier Service", private readonly string $clientVersion = "1.0.0", private readonly string $requestedProtocolVersion = MCPProtocolVersionLatestStable, private readonly float $catalogTimeout = 30.0)
    {
        if ($catalogTimeout <= 0.0) {
            throw new InvalidArgumentException("catalogTimeout must be greater than zero.");
        }
    }

    /**
     * Calls one remote tool through the initialized MCP session.
     *
     * @param string $name The remote tool name.
     * @param Dictionary<mixed> $arguments The arguments supplied by the model.
     * @param float|null $timeout Seconds available for this exchange, or `null` for the transport default.
     * @return ToolCallResult The remote content and its correctable-error flag.
     * @throws MCPClientException When initialization, transport or protocol validation fails.
     */
    public function callTool(string $name, Dictionary $arguments, ?float $timeout = null): ToolCallResult
    {
        $this->initialize();
        $result = $this->request(MCPMethod::toolsCall, new Dictionary(["name" => $name, "arguments" => $arguments]), $timeout);
        $content = $result["content"] ?? null;
        if (!is_array($content) || !array_is_list($content)) {
            throw new MCPClientException("The MCP server returned a tool result without a content list.", false);
        }
        /** @var ArrayClass<ContentItem> $items */
        $items = new ArrayClass($content)->map(function (mixed $value): ContentItem {
            if (!is_array($value) || ($value["type"] ?? null) !== "text" || !is_string($value["text"] ?? null)) {
                throw new MCPClientException("The MCP server returned unsupported tool content.", false);
            }
            return new ContentItem("text", $value["text"]);
        });
        $isError = $result["isError"] ?? null;
        if ($isError !== null && !is_bool($isError)) {
            throw new MCPClientException("The MCP server returned a non-boolean tool error flag.", false);
        }
        return new ToolCallResult($items, $isError === true);
    }

    /** @return ArrayClass<ToolDescriptor> */
    private function loadTools(): ArrayClass
    {
        $this->initialize();
        $result = $this->request(MCPMethod::toolsList, timeout: $this->catalogTimeout);
        $tools = $result["tools"] ?? null;
        if (!is_array($tools) || !array_is_list($tools)) {
            throw new MCPClientException("The MCP server returned a catalogue without a tools list.", false);
        }
        /** @var ArrayClass<ToolDescriptor> */
        return new ArrayClass($tools)->map(function (mixed $value): ToolDescriptor {
            if (!is_array($value) || !is_string($value["name"] ?? null) || trim($value["name"]) === "") {
                throw new MCPClientException("The MCP server returned a tool without a usable name.", false);
            }
            $name = $value["name"];
            $description = $value["description"] ?? null;
            $title = $value["title"] ?? null;
            $schema = $value["inputSchema"] ?? null;
            $annotations = $value["annotations"] ?? null;
            if (($description !== null && !is_string($description)) || ($title !== null && !is_string($title)) || !is_array($schema)) {
                throw new MCPClientException("The MCP server returned an invalid descriptor for $name.", false);
            }
            if ($annotations !== null) {
                if (!is_array($annotations) || ($annotations !== [] && array_is_list($annotations))) {
                    throw new MCPClientException("The MCP server returned invalid annotations for $name.", false);
                }
                foreach ($annotations as $key => $hint) {
                    if (($key === "title" && !is_string($hint)) || (in_array($key, ["readOnlyHint", "destructiveHint", "idempotentHint", "openWorldHint"], true) && !is_bool($hint))) {
                        throw new MCPClientException("The MCP server returned an invalid $key annotation for $name.", false);
                    }
                }
            }
            return new ToolDescriptor($name, (string)$description, $schema, $title, $annotations);
        });
    }

    private function initialize(): void
    {
        if ($this->sessionIdentifier !== null) {
            return;
        }
        $params = new Dictionary([
            "protocolVersion" => $this->requestedProtocolVersion,
            "capabilities" => new stdClass(),
            "clientInfo" => new Dictionary(["name" => $this->clientName, "version" => $this->clientVersion]),
        ]);
        [$result, $response] = $this->exchange(MCPMethod::initialize, $params, $this->catalogTimeout);
        $version = $result["protocolVersion"] ?? null;
        $sessionIdentifier = $response->headers->valueForCaseInsensitiveKey(MCPSessionHeader);
        if (!is_string($version) || trim($version) === "" || !is_string($sessionIdentifier) || trim($sessionIdentifier) === "") {
            throw new MCPClientException("The MCP server did not establish a usable session.", false);
        }
        $this->protocolVersion = $version;
        $this->sessionIdentifier = $sessionIdentifier;
        $message = new Dictionary(["jsonrpc" => "2.0", "method" => MCPMethod::initialized]);
        $this->validateTransportResponse($this->transport->send($message, $this->headers(), $this->catalogTimeout));
    }

    /** @return array<string, mixed> */
    private function request(string $method, ?Dictionary $params = null, ?float $timeout = null): array
    {
        [$result,] = $this->exchange($method, $params, $timeout);
        return $result;
    }

    /** @return array{array<string, mixed>, MCPClientResponse} */
    private function exchange(string $method, ?Dictionary $params, ?float $timeout): array
    {
        $identifier = $this->nextIdentifier++;
        /** @var Dictionary<mixed> $message */
        $message = new Dictionary(["jsonrpc" => "2.0", "id" => $identifier, "method" => $method]);
        if ($params !== null && !$params->isEmpty) {
            $message["params"] = $params;
        }
        $response = $this->transport->send($message, $this->headers(), $timeout);
        $this->validateTransportResponse($response);
        $body = trim($response->body);
        if ($body === "") {
            throw new MCPClientException("The MCP server returned an empty JSON-RPC response.", true);
        }
        try {
            $envelope = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new MCPClientException("The MCP server returned malformed JSON: {$exception->getMessage()}", true, $exception);
        }
        if (!is_array($envelope) || array_is_list($envelope)) {
            throw new MCPClientException("The MCP server returned a JSON-RPC response that is not an object.", false);
        }
        if (($envelope["jsonrpc"] ?? null) !== "2.0" || ($envelope["id"] ?? null) !== $identifier) {
            throw new MCPClientException("The MCP server returned a mismatched JSON-RPC response.", false);
        }
        $error = $envelope["error"] ?? null;
        if ($error !== null) {
            if (!is_array($error) || array_is_list($error)) {
                throw new MCPClientException("The MCP server returned an invalid JSON-RPC error object.", false);
            }
            $message = is_string($error["message"] ?? null) ? $error["message"] : "The MCP server returned a JSON-RPC error.";
            throw new MCPClientException($message, false);
        }
        $result = $envelope["result"] ?? null;
        if (!is_array($result) || array_is_list($result)) {
            throw new MCPClientException("The MCP server returned a JSON-RPC response without an object result.", false);
        }
        return [$result, $response];
    }

    private function validateTransportResponse(MCPClientResponse $response): void
    {
        if (in_range($response->statusCode, 200, 300)) {
            return;
        }
        if ($response->statusCode === 404) {
            $this->sessionIdentifier = null;
            $this->protocolVersion = null;
            $this->cachedTools = null;
        }
        $body = trim($response->body);
        $message = "The MCP server returned HTTP $response->statusCode";
        $isTransient = in_array($response->statusCode, [404, 408, 429], true) || $response->statusCode >= 500;
        throw new MCPClientException($body === "" ? "$message." : "$message: $body", $isTransient);
    }

    /** @return Dictionary<string> */
    private function headers(): Dictionary
    {
        /** @var Dictionary<string> */
        return new Dictionary([MCPSessionHeader => $this->sessionIdentifier, MCPProtocolVersionHeader => $this->protocolVersion]);
    }

}
