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
use Sabatier\Service\MCP\Tools\FetchHistoryTool;
use Sabatier\Service\MCP\Tools\PurgeHistoryTool;

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
    public function readingAndPurgingHistoryAreSeparateTools(): void
    {
        /** @var FetchHistoryTool $fetch */
        $fetch = new ReflectionClass(FetchHistoryTool::class)->newInstanceWithoutConstructor();
        /** @var PurgeHistoryTool $purge */
        $purge = new ReflectionClass(PurgeHistoryTool::class)->newInstanceWithoutConstructor();

        $this->assertTrue($fetch->isReadOnlyCall(new Dictionary()));
        $this->assertFalse($purge->isReadOnlyCall(new Dictionary()));
    }
}
