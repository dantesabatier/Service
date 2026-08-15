<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Service\MCP\InitializeHandler;
use Sabatier\Service\MCP\JSONRPCRequestParser;
use Sabatier\Service\MCP\MCPRequestHandler;
use Sabatier\Service\MCP\MCPTransportGuard;
use Sabatier\Service\MCP\MethodDispatcher;
use Sabatier\Service\MCP\Schema\AttributeSchemaFactory;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\Schema\ModelSchemaExtractor;
use Sabatier\Service\MCP\Schema\PredicateGuideFactory;
use Sabatier\Service\MCP\Schema\SchemaLocalizer;
use Sabatier\Service\MCP\Schema\VocabularyRepository;
use Sabatier\Service\MCP\ToolResolver;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Sabatier\Service\MCP\ToolsCallHandler;
use Sabatier\Service\MCP\ToolsListHandler;

#[Endpoint("/mcp", [JSONRPCTransformer::class, JSONTransformer::class, MCPSessionTransformer::class])]
/**
 * Endpoint that exposes the MCP protocol (GET/POST/DELETE).
 *
 * Serves as the public entrypoint for MCP clients. Every request passes through
 * `MCPTransportGuard` before dispatch, which enforces the Streamable HTTP transport rules:
 * the origin a browser declares, the session the handshake issued, and the negotiated
 * protocol version. The guard governs the transport only — the caller's identity is
 * established by the access evaluator chain, on this endpoint as on every other.
 *
 * @see MCPTransportGuard
 */
final class MCPResponder extends Responder
{
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::delete]);
    }
    private ModelDescriptor $descriptor {
        get => $this->descriptor ??= new ModelDescriptor(new ModelSchemaExtractor($this->managedObjectContext, new AttributeSchemaFactory()), new VocabularyRepository(), new SchemaLocalizer(), new PredicateGuideFactory());
    }
    private ToolRegistry $registry {
        get => $this->registry ??= new ToolRegistry(new ToolResolver($this->managedObjectContext, $this->descriptor)->resolve());
    }
    /** @var string The username of the authenticated identity, or an empty string when the request carries none. Scopes every session this responder reads or writes. */
    private string $subject {
        get => $this->subject ??= Application::shared()->authenticationManager->authentication->authenticatedUser?->username ?? "";
    }
    private MCPTransportGuard $guard {
        get => $this->guard ??= new MCPTransportGuard($this->subject);
    }
    private InitializeHandler $initializeHandler {
        get => $this->initializeHandler ??= new InitializeHandler();
    }
    /**
     * Rebuilt on every read rather than memoized, since the session identifier only comes into
     * existence once the handshake has run. `Responder` reads the body before this context for
     * exactly that reason.
     */
    #[Override]
    protected ResponseTransformerContext $transformerContext {
        get => new ResponseTransformerContext($this->request, $this->cachePolicy, $this->corsPolicy, $this->securityHeadersPolicy, Application::shared()->rateLimitInfo, mcpSessionIdentifier: $this->initializeHandler->sessionIdentifier);
    }
    #[Override]
    protected mixed $data {
        get => $this->data ??= $this->handle();
    }

    private function handle(): mixed
    {
        $request = $this->request;
        $method = $this->method();
        $this->guard->enforce($request, $method);
        if ($request->httpMethod === HTTPRequestMethod::delete) {
            $this->guard->endSession($request);
            $this->statusCode = HTTPStatusCode::noContent;
            return null;
        }
        $this->guard->touchSession($request);
        return new MCPRequestHandler(new JSONRPCRequestParser(), new MethodDispatcher($this->initializeHandler, new ToolsListHandler($this->registry), new ToolsCallHandler($this->registry)))->handle($request);
    }

    /**
     * Returns the JSON-RPC method the body invokes, or null when it names none.
     *
     * Read straight from the request rather than from the parsed message, because the guard
     * runs before parsing and a notification parses to no message at all.
     */
    private function method(): ?string
    {
        $method = $this->request->parameters["method"];
        return is_string($method) && $method !== "" ? $method : null;
    }
}
