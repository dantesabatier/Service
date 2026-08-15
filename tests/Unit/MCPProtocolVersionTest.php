<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Service\MCP\MCPProtocolVersion;
use const Sabatier\Service\MCPProtocolVersionLatestStable;
use const Sabatier\Service\MCPProtocolVersionLegacy;
use const Sabatier\Service\MCPProtocolVersionStable;

/**
 * Negotiation answers with the version the client asked for when the server implements it,
 * and with the latest one it does implement when it does not. Answering with something the
 * server does not speak would leave the client nothing to do but disconnect.
 */
final class MCPProtocolVersionTest extends TestCase
{
    #[Test]
    public function everySupportedVersionIsAnsweredWithItself(): void
    {
        foreach ([MCPProtocolVersionLegacy, MCPProtocolVersionStable, MCPProtocolVersionLatestStable] as $version) {
            $this->assertSame($version, MCPProtocolVersion::negotiate($version));
        }
    }

    #[Test]
    public function unsupportedVersionFallsBackToTheLatest(): void
    {
        $this->assertSame(MCPProtocolVersionLatestStable, MCPProtocolVersion::negotiate("1999-01-01"));
    }

    #[Test]
    public function absentVersionFallsBackToTheLatest(): void
    {
        $this->assertSame(MCPProtocolVersionLatestStable, MCPProtocolVersion::negotiate(null));
    }

    #[Test]
    public function theSupportedSetHoldsEveryDeclaredVersion(): void
    {
        $supported = MCPProtocolVersion::supported();
        $this->assertTrue($supported->containsElement(MCPProtocolVersionLegacy));
        $this->assertTrue($supported->containsElement(MCPProtocolVersionStable));
        $this->assertTrue($supported->containsElement(MCPProtocolVersionLatestStable));
    }
}
