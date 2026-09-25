<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\LLM\LLMExecutionPolicy;
use Sabatier\Service\LLM\LLMToolCall;
use Sabatier\Service\MCP\Tools\PersistentHistoryTool;

final class LLMExecutionPolicyTest extends TestCase
{
    #[Test]
    public function writesAreDeniedByDefaultAndMayBeApprovedPerCall(): void
    {
        $call = new LLMToolCall("call-1", "update", new Dictionary(["objectID" => 42]));

        $this->assertFalse(new LLMExecutionPolicy()->approvesWrite($call));
        $this->assertTrue(new LLMExecutionPolicy(writeApproval: static fn(LLMToolCall $candidate): bool => $candidate->name === "update" && $candidate->arguments["objectID"] === 42)->approvesWrite($call));
    }

    #[Test]
    public function negativeLimitsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LLMExecutionPolicy(maxToolCalls: -1);
    }

    #[Test]
    public function negativeSubagentIterationLimitIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LLMExecutionPolicy(maxSubagentIterations: -1);
    }

    /** @throws ReflectionException */
    #[Test]
    public function mixedToolsClassifyTheConcreteOperation(): void
    {
        /** @var PersistentHistoryTool $tool */
        $tool = new ReflectionClass(PersistentHistoryTool::class)->newInstanceWithoutConstructor();

        $this->assertTrue($tool->isReadOnlyCall(new Dictionary(["operation" => "fetch"])));
        $this->assertFalse($tool->isReadOnlyCall(new Dictionary(["operation" => "purge"])));
    }
}
