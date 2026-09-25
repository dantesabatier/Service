<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;
use Sabatier\Service\LLM\LLMToolCall;
use Sabatier\Service\LLM\WindowedLLMContextAssembler;
use Sabatier\Service\MCP\Response\ToolDescriptor;

final class WindowedLLMContextAssemblerTest extends TestCase
{
    #[Test]
    public function anUnboundedAssemblerPreservesTheCompleteContextWithoutMutatingIt(): void
    {
        $messages = new ArrayClass([new LLMMessage(LLMMessageRole::user, "hello")]);

        $context = new WindowedLLMContextAssembler()->assemble($messages, new ArrayClass(), "system");

        $this->assertNotSame($messages, $context->messages);
        $this->assertSame("hello", $context->messages->first->content);
        $this->assertSame("system", $context->systemPrompt);
        $this->assertSame(0, $context->omittedMessageCount);
        $this->assertTrue($context->isWithinLimit);
    }

    #[Test]
    public function truncationRemovesACompleteToolTurnInsteadOfSplittingItsResultFromItsCall(): void
    {
        $messages = new ArrayClass([
            new LLMMessage(LLMMessageRole::user, "old task"),
            new LLMMessage(LLMMessageRole::assistant, null, new ArrayClass([new LLMToolCall("call-1", "probe", new Dictionary())])),
            new LLMMessage(LLMMessageRole::tool, "old result", toolCallId: "call-1"),
            new LLMMessage(LLMMessageRole::user, "latest task"),
        ]);

        $context = new WindowedLLMContextAssembler(maximumMessages: 1)->assemble($messages, new ArrayClass());

        $this->assertSame(3, $context->omittedMessageCount);
        $this->assertSame("latest task", $context->messages->first->content);
        $this->assertTrue($context->isWithinLimit);
    }

    #[Test]
    public function systemMessagesAndTheLatestTurnAreNeverDiscarded(): void
    {
        $messages = new ArrayClass([
            new LLMMessage(LLMMessageRole::system, "policy"),
            new LLMMessage(LLMMessageRole::user, "old task"),
            new LLMMessage(LLMMessageRole::assistant, "old answer"),
            new LLMMessage(LLMMessageRole::user, "latest task"),
        ]);

        $context = new WindowedLLMContextAssembler(maximumMessages: 2)->assemble($messages, new ArrayClass());

        $this->assertSame(2, $context->omittedMessageCount);
        $this->assertSame(LLMMessageRole::system, $context->messages[0]->role);
        $this->assertSame("latest task", $context->messages[1]->content);
    }

    #[Test]
    public function omittedTurnsMayBeCompactedIntoAUserMessageWhenTheSummaryFits(): void
    {
        $messages = new ArrayClass([
            new LLMMessage(LLMMessageRole::user, "old task"),
            new LLMMessage(LLMMessageRole::assistant, "old answer"),
            new LLMMessage(LLMMessageRole::user, "latest task"),
        ]);
        $assembler = new WindowedLLMContextAssembler(maximumMessages: 2, compressor: fn(ArrayClass $omitted): string => "Compressed $omitted->count messages.");

        $context = $assembler->assemble($messages, new ArrayClass());

        $this->assertTrue($context->wasCompacted);
        $this->assertStringContainsString("Compressed 2 messages.", (string)$context->messages[0]->content);
        $this->assertSame("latest task", $context->messages[1]->content);
    }

    #[Test]
    public function aLatestTurnThatCannotFitIsReportedInsteadOfBeingSilentlyDropped(): void
    {
        $messages = new ArrayClass([new LLMMessage(LLMMessageRole::user, "latest task")]);

        $context = new WindowedLLMContextAssembler(maximumMessages: 0)->assemble($messages, new ArrayClass());

        $this->assertFalse($context->isWithinLimit);
        $this->assertSame("latest task", $context->messages->first->content);
    }

    #[Test]
    public function aCustomMeasureCanApplyProviderSpecificTokenAccounting(): void
    {
        $messages = new ArrayClass([new LLMMessage(LLMMessageRole::user, "latest task")]);
        $assembler = new WindowedLLMContextAssembler(maximumSize: 4, measure: fn(ArrayClass $history, ArrayClass $tools, ?string $prompt): int => $history->count + $tools->count + strlen((string)$prompt));

        $context = $assembler->assemble($messages, new ArrayClass(), "four");

        $this->assertFalse($context->isWithinLimit);
    }

    #[Test]
    public function defaultMeasurementCarriesTheAccumulatorFromToolsIntoMessages(): void
    {
        $messages = new ArrayClass([new LLMMessage(LLMMessageRole::user, "x")]);
        $tools = new ArrayClass([new ToolDescriptor("a", "b", [])]);

        $messageOnly = new WindowedLLMContextAssembler(maximumSize: 17)->assemble($messages, new ArrayClass());
        $toolOnly = new WindowedLLMContextAssembler(maximumSize: 4)->assemble(new ArrayClass(), $tools);
        $combined = new WindowedLLMContextAssembler(maximumSize: 17)->assemble($messages, $tools);

        $this->assertTrue($messageOnly->isWithinLimit);
        $this->assertTrue($toolOnly->isWithinLimit);
        $this->assertFalse($combined->isWithinLimit);
    }
}
