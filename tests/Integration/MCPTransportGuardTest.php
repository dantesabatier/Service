<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Service\Application;
use Sabatier\Service\BadRequestException;
use Sabatier\Service\ForbiddenException;
use Sabatier\Service\InMemoryMCPSessionStore;
use Sabatier\Service\MCP\MCPSessionKey;
use Sabatier\Service\MCP\MCPTransportGuard;
use Sabatier\Service\MCPSession;
use Sabatier\Service\NotFoundException;
use Sabatier\Service\Request;
use const Sabatier\Service\MCPAllowedOriginsKey;
use const Sabatier\Service\MCPProtocolVersionLegacy;
use const Sabatier\Service\MCPProtocolVersionStable;

/**
 * Covers the transport rules the Streamable HTTP specification requires before a message is
 * dispatched: the origin a browser declares, the session the handshake issued, and the
 * negotiated protocol version.
 *
 * Every session is stored under a key that includes its owner, so the subject is passed in
 * explicitly rather than resolved against the request in flight.
 */
final class MCPTransportGuardTest extends TestCase
{
    private const string subject = "operator";
    private const string otherSubject = "someone-else";

    private array $originalServer;
    private mixed $originalAllowedOrigins;

    #[Override]
    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["REQUEST_URI"] = "/mcp";
        $_SERVER["REQUEST_METHOD"] = "POST";
        unset($_SERVER["HTTP_ORIGIN"], $_SERVER["HTTP_MCP_SESSION_ID"], $_SERVER["HTTP_MCP_PROTOCOL_VERSION"]);
        $environment = ProcessInfo::processInfo()->environment;
        $this->originalAllowedOrigins = $environment[MCPAllowedOriginsKey];
        $environment[MCPAllowedOriginsKey] = "https://allowed.example";
        Application::shared()->mcpSessionStore = new InMemoryMCPSessionStore();
        InMemoryMCPSessionStore::reset();
    }

    #[Override]
    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        ProcessInfo::processInfo()->environment[MCPAllowedOriginsKey] = $this->originalAllowedOrigins;
        InMemoryMCPSessionStore::reset();
    }

    private function guard(string $subject = self::subject): MCPTransportGuard
    {
        return new MCPTransportGuard($subject);
    }

    private function storedSessionIdentifier(string $subject = self::subject): string
    {
        $identifier = "session-under-test";
        Application::shared()->mcpSessionStore->store(new MCPSessionKey()->key($subject, $identifier), new MCPSession($subject, MCPProtocolVersionStable), 3600);
        return $identifier;
    }

    // --- Origin ---

    #[Test]
    public function requestWithoutOriginIsAllowed(): void
    {
        $this->guard()->enforce(new Request(), "initialize");
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function allowedOriginIsAccepted(): void
    {
        $_SERVER["HTTP_ORIGIN"] = "https://allowed.example";
        $this->guard()->enforce(new Request(), "initialize");
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function disallowedOriginIsRejected(): void
    {
        $_SERVER["HTTP_ORIGIN"] = "https://evil.example";
        $this->expectException(ForbiddenException::class);
        $this->guard()->enforce(new Request(), "initialize");
    }

    // --- Session ---

    #[Test]
    public function handshakeIsAllowedWithoutSession(): void
    {
        $this->guard()->enforce(new Request(), "initialize");
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function pingIsAllowedWithoutSession(): void
    {
        $this->guard()->enforce(new Request(), "ping");
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function toolCallWithoutSessionIsRejected(): void
    {
        $this->expectException(BadRequestException::class);
        $this->guard()->enforce(new Request(), "tools/call");
    }

    #[Test]
    public function toolCallWithUnknownSessionIsRejected(): void
    {
        $_SERVER["HTTP_MCP_SESSION_ID"] = "never-issued";
        $this->expectException(NotFoundException::class);
        $this->guard()->enforce(new Request(), "tools/call");
    }

    #[Test]
    public function toolCallWithKnownSessionIsAllowed(): void
    {
        $_SERVER["HTTP_MCP_SESSION_ID"] = $this->storedSessionIdentifier();
        $this->guard()->enforce(new Request(), "tools/call");
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function sessionOfAnotherSubjectIsRejected(): void
    {
        $_SERVER["HTTP_MCP_SESSION_ID"] = $this->storedSessionIdentifier(self::otherSubject);
        $this->expectException(NotFoundException::class);
        $this->guard()->enforce(new Request(), "tools/call");
    }

    #[Test]
    public function touchingASessionReturnsIt(): void
    {
        $_SERVER["HTTP_MCP_SESSION_ID"] = $this->storedSessionIdentifier();
        $session = $this->guard()->touchSession(new Request());
        $this->assertInstanceOf(MCPSession::class, $session);
        $this->assertSame(MCPProtocolVersionStable, $session->protocolVersion);
    }

    #[Test]
    public function endingASessionRemovesIt(): void
    {
        $_SERVER["HTTP_MCP_SESSION_ID"] = $this->storedSessionIdentifier();
        $guard = $this->guard();
        $guard->endSession(new Request());
        $this->assertNull($guard->touchSession(new Request()));
    }

    // --- Protocol version ---

    #[Test]
    public function absentProtocolVersionIsAllowed(): void
    {
        $this->guard()->enforce(new Request(), "initialize");
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function supportedProtocolVersionIsAllowed(): void
    {
        $_SERVER["HTTP_MCP_PROTOCOL_VERSION"] = MCPProtocolVersionLegacy;
        $this->guard()->enforce(new Request(), "initialize");
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function unsupportedProtocolVersionIsRejected(): void
    {
        $_SERVER["HTTP_MCP_PROTOCOL_VERSION"] = "1999-01-01";
        $this->expectException(BadRequestException::class);
        $this->guard()->enforce(new Request(), "initialize");
    }
}
