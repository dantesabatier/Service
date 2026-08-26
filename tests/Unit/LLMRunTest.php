<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;
use Sabatier\Service\LLM\LLMRun;
use Sabatier\Service\LLM\LLMRunStopReason;
use Sabatier\Service\LLM\LLMToolCall;

/** Covers how a run reports the tool calls it made, the pairing anything persisting a run would otherwise have to redo by hand. */
final class LLMRunTest extends TestCase
{
    #[Test]
    public function eachCallIsPairedWithItsOwnResult(): void
    {
        $run = new LLMRun(new ArrayClass([
            new LLMMessage(LLMMessageRole::assistant, null, new ArrayClass([
                new LLMToolCall("a", "first_tool", new Dictionary(["n" => 1])),
                new LLMToolCall("b", "second_tool", new Dictionary()),
            ])),
            new LLMMessage(LLMMessageRole::tool, "second answer", toolCallId: "b"),
            new LLMMessage(LLMMessageRole::tool, "first answer", toolCallId: "a"),
            new LLMMessage(LLMMessageRole::assistant, "done"),
        ]));

        $results = $run->toolCallResults;

        $this->assertSame(2, $results->count);
        $this->assertSame("first_tool", $results[0]->call->name);
        $this->assertSame("first answer", $results[0]->content);
        $this->assertSame("second answer", $results[1]->content);
    }

    #[Test]
    public function aFailedCallKeepsItsFailureFlagAndItsMessage(): void
    {
        $run = new LLMRun(new ArrayClass([
            new LLMMessage(LLMMessageRole::assistant, null, new ArrayClass([new LLMToolCall("a", "probe_tool", new Dictionary())])),
            new LLMMessage(LLMMessageRole::tool, "the query was malformed", toolCallId: "a", isError: true),
            new LLMMessage(LLMMessageRole::assistant, "done"),
        ]));

        $result = $run->toolCallResults[0];

        $this->assertTrue($result->isError);
        $this->assertSame("the query was malformed", $result->content);
    }

    #[Test]
    public function aSuccessfulCallIsNotFlagged(): void
    {
        $run = new LLMRun(new ArrayClass([
            new LLMMessage(LLMMessageRole::assistant, null, new ArrayClass([new LLMToolCall("a", "probe_tool", new Dictionary())])),
            new LLMMessage(LLMMessageRole::tool, "42 rows", toolCallId: "a"),
            new LLMMessage(LLMMessageRole::assistant, "done"),
        ]));

        $this->assertFalse($run->toolCallResults[0]->isError);
    }

    #[Test]
    public function aCallTheRunNeverAnsweredIsStillReported(): void
    {
        $run = new LLMRun(new ArrayClass([
            new LLMMessage(LLMMessageRole::assistant, null, new ArrayClass([new LLMToolCall("a", "probe_tool", new Dictionary())])),
        ]), stopReason: LLMRunStopReason::providerFailure);

        $results = $run->toolCallResults;

        $this->assertSame(1, $results->count);
        $this->assertNull($results[0]->content);
        $this->assertFalse($results[0]->isError);
    }

    #[Test]
    public function aRunThatCalledNothingReportsNothing(): void
    {
        $run = new LLMRun(new ArrayClass([new LLMMessage(LLMMessageRole::assistant, "just an answer")]));

        $this->assertTrue($run->toolCallResults->isEmpty);
    }
}
