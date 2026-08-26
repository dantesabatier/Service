<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Service\AccessEvaluationContext;
use Sabatier\Service\Authentication;
use Sabatier\Service\BasicAuthentication;
use Sabatier\Service\BearerAuthentication;
use Sabatier\Service\JSONWebToken;
use Sabatier\Service\JSONWebTokenAudienceEvaluator;
use Sabatier\Service\JSONWebTokenHeader;
use Sabatier\Service\JSONWebTokenPayload;
use Sabatier\Service\Request;
use const Sabatier\Service\MCPTokenAudienceKey;

/**
 * The audience binds a token to the resource it was issued for. Without it, the token a user
 * receives by signing in to the application opens MCP as well, because nothing distinguishes
 * it from one deliberately issued for that purpose.
 *
 * The evaluator stays inert until an audience is configured, so the code can ship before the
 * tokens that carry one exist. Once configured it is required outright: a token without the
 * claim stops reaching MCP.
 */
final class JSONWebTokenAudienceEvaluatorTest extends TestCase
{
    private const string requiredAudience = "raya.mcp";

    private array $originalServer;
    private mixed $originalAudience;

    #[Override]
    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["REQUEST_METHOD"] = "POST";
        $this->originalAudience = ProcessInfo::processInfo()->environment[MCPTokenAudienceKey];
    }

    #[Override]
    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        ProcessInfo::processInfo()->environment[MCPTokenAudienceKey] = $this->originalAudience;
    }

    private function context(string $path, Authentication $authentication, string $requiredAudience): AccessEvaluationContext
    {
        $_SERVER["REQUEST_URI"] = $path;
        ProcessInfo::processInfo()->environment[MCPTokenAudienceKey] = $requiredAudience;
        $reflection = new ReflectionClass(AccessEvaluationContext::class);
        $context = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty("request")->setValue($context, new Request());
        $reflection->getProperty("authentication")->setValue($context, $authentication);
        $reflection->getProperty("environment")->setValue($context, ProcessInfo::processInfo()->environment);
        return $context;
    }

    private function bearerWithAudience(?string $audience): Authentication
    {
        $authentication = new ReflectionClass(BearerAuthentication::class)->newInstanceWithoutConstructor();
        $token = new JSONWebToken(new JSONWebTokenHeader(), new JSONWebTokenPayload(audience: $audience));
        $reflection = new ReflectionClass(BearerAuthentication::class);
        $reflection->getProperty("token")->setValue($authentication, $token);
        $reflection->getProperty("isTokenResolved")->setValue($authentication, true);
        return $authentication;
    }

    private function nonBearer(): Authentication
    {
        return new ReflectionClass(BasicAuthentication::class)->newInstanceWithoutConstructor();
    }

    #[Test]
    public function anUnconfiguredAudienceAcceptsATokenWithoutOne(): void
    {
        $this->assertTrue(new JSONWebTokenAudienceEvaluator()->evaluate($this->context("/mcp", $this->bearerWithAudience(null), "")));
    }

    #[Test]
    public function theMatchingAudienceIsAccepted(): void
    {
        $this->assertTrue(new JSONWebTokenAudienceEvaluator()->evaluate($this->context("/mcp", $this->bearerWithAudience(self::requiredAudience), self::requiredAudience)));
    }

    #[Test]
    public function aTokenWithoutTheAudienceIsRejected(): void
    {
        $this->assertFalse(new JSONWebTokenAudienceEvaluator()->evaluate($this->context("/mcp", $this->bearerWithAudience(null), self::requiredAudience)));
    }

    #[Test]
    public function aTokenForAnotherResourceIsRejected(): void
    {
        $this->assertFalse(new JSONWebTokenAudienceEvaluator()->evaluate($this->context("/mcp", $this->bearerWithAudience("some.other.resource"), self::requiredAudience)));
    }

    #[Test]
    public function thePathIsMatchedRegardlessOfCase(): void
    {
        $this->assertFalse(new JSONWebTokenAudienceEvaluator()->evaluate($this->context("/MCP", $this->bearerWithAudience(null), self::requiredAudience)));
    }

    #[Test]
    public function otherEndpointsAreUnaffected(): void
    {
        $this->assertTrue(new JSONWebTokenAudienceEvaluator()->evaluate($this->context("/Capacity/Committed", $this->bearerWithAudience(null), self::requiredAudience)));
    }

    #[Test]
    public function aNonBearerSchemeIsUnaffected(): void
    {
        $this->assertTrue(new JSONWebTokenAudienceEvaluator()->evaluate($this->context("/mcp", $this->nonBearer(), self::requiredAudience)));
    }
}
