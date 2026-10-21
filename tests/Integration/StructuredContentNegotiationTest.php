<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\Application;
use Sabatier\Service\InMemoryMCPSessionStore;
use Sabatier\Service\MCP\MCPSessionKey;
use Sabatier\Service\MCP\MCPTransportGuard;
use Sabatier\Service\MCP\Response\ToolCallResult;
use Sabatier\Service\MCP\RPCMessage;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Sabatier\Service\MCP\ToolsCallHandler;
use Sabatier\Service\MCPSession;
use Sabatier\Service\Request;
use Sabatier\Service\Tests\Unit\StructuredProbeTool;
use Throwable;
use const Sabatier\Service\MCPProtocolVersionLatestStable;
use const Sabatier\Service\MCPProtocolVersionLegacy;
use const Sabatier\Service\MCPSessionHeader;

/**
 * Covers the chain the responder assembles: the revision the handshake stored on the session is what decides whether
 * a tool's structured payload reaches the client, read back through the guard exactly as a request in flight does.
 */
final class StructuredContentNegotiationTest extends TestCase
{
    private const string subject = "operator";
    private const string identifier = "session-under-test";

    private array $originalServer;

    #[Override]
    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["REQUEST_URI"] = "/mcp";
        $_SERVER["REQUEST_METHOD"] = "POST";
        Application::shared()->mcpSessionStore = new InMemoryMCPSessionStore();
        InMemoryMCPSessionStore::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        InMemoryMCPSessionStore::reset();
    }

    /** @throws Throwable */
    #[Test]
    public function aSessionOnTheLatestRevisionReceivesTheStructuredPayload(): void
    {
        $this->assertSame(["rowCount" => 1, "results" => ["ok"]], $this->envelope(MCPProtocolVersionLatestStable)["structuredContent"] ?? null);
    }

    /** @throws Throwable */
    #[Test]
    public function aSessionOnTheLegacyRevisionReceivesTheContentWithoutIt(): void
    {
        $envelope = $this->envelope(MCPProtocolVersionLegacy);
        $this->assertArrayNotHasKey("structuredContent", $envelope);
        $this->assertArrayHasKey("content", $envelope);
    }

    /**
     * Stores a session on the given revision, reads it back through the guard as the responder does, and returns the
     * serialized `tools/call` envelope the client would be sent.
     *
     * @return array<array-key, mixed>
     * @throws Throwable
     */
    private function envelope(string $protocolVersion): array
    {
        Application::shared()->mcpSessionStore->store(new MCPSessionKey()->key(self::subject, self::identifier), new MCPSession(self::subject, $protocolVersion), 3600);
        $_SERVER["HTTP_" . strtoupper(str_replace("-", "_", MCPSessionHeader))] = self::identifier;
        $session = new MCPTransportGuard(self::subject)->touchSession(new Request());
        $this->assertInstanceOf(MCPSession::class, $session);
        $registry = new ToolRegistry(new ArrayClass([new ReflectionClass(StructuredProbeTool::class)->newInstanceWithoutConstructor()]));
        $message = new RPCMessage(1, "tools/call", new Dictionary(["name" => "structured_probe", "arguments" => new Dictionary()]));
        /** @var ToolCallResult $result */
        $result = new ToolsCallHandler($registry, $session->protocolVersion)->handle($message);
        return $result->jsonSerialize();
    }
}
