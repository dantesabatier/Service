<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\URL;
use Sabatier\Service\MCPSessionTransformer;
use Sabatier\Service\Response;
use Sabatier\Service\ResponseTransformerContext;
use const Sabatier\Service\MCPSessionHeader;

/**
 * The handshake response is the only chance the client has to learn the session identifier,
 * since every later request must echo it back. A response that establishes no session passes
 * through untouched.
 */
final class MCPSessionTransformerTest extends TestCase
{
    private function transform(?string $identifier): Response
    {
        return new MCPSessionTransformer(new Response(new URL("http://localhost/mcp")), new ResponseTransformerContext(mcpSessionIdentifier: $identifier))->response;
    }

    #[Test]
    public function theHeaderCarriesTheIssuedIdentifier(): void
    {
        $this->assertSame("issued-identifier", $this->transform("issued-identifier")->allHeaderFields[MCPSessionHeader]);
    }

    #[Test]
    public function noHeaderWhenNoSessionWasEstablished(): void
    {
        $this->assertNull($this->transform(null)->allHeaderFields[MCPSessionHeader]);
    }
}
