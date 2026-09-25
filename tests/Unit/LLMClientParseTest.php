<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\LLM\LLMProviderException;
use Sabatier\Service\LLM\AnthropicClient;
use Sabatier\Service\LLM\LLMClient;
use Sabatier\Service\LLM\LLMTurn;
use Sabatier\Service\LLM\LLMTurnStopReason;
use Sabatier\Service\LLM\OllamaClient;
use Sabatier\Service\LLM\StandardLLMClient;

/**
 * Covers the provider-specific deserialization each client owns, the layer `LLMAgentTest` deliberately scripts past.
 *
 * Every case feeds a raw JSON body decoded exactly as {@see LLMClient::send()} decodes it, so a test fails on the wire format the provider actually sends rather than on a hand-built `Dictionary` that already assumes the shape under test.
 */
final class LLMClientParseTest extends TestCase
{
    #[Test]
    public function chatCompletionsUsageIsCountedUnderItsOwnKeys(): void
    {
        $turn = $this->parse(new StandardLLMClient(), "{\"choices\":[{\"message\":{\"content\":\"hi\"}}],\"usage\":{\"prompt_tokens\":137,\"completion_tokens\":42}}");

        $this->assertSame(137, $turn->inputTokens);
        $this->assertSame(42, $turn->outputTokens);
    }

    #[Test]
    public function responsesApiUsageIsStillCounted(): void
    {
        $turn = $this->parse(new StandardLLMClient(), "{\"choices\":[{\"message\":{\"content\":\"hi\"}}],\"usage\":{\"input_tokens\":11,\"output_tokens\":5}}");

        $this->assertSame(11, $turn->inputTokens);
        $this->assertSame(5, $turn->outputTokens);
    }

    #[Test]
    public function standardClientReportsTheProviderErrorBody(): void
    {
        $this->expectException(LLMProviderException::class);

        $this->parse(new StandardLLMClient(), "{\"error\":{\"message\":\"model not found\",\"type\":\"invalid_request_error\"}}");
    }

    #[Test]
    public function standardClientDistinguishesCompletionOutputLimitAndRefusal(): void
    {
        $completed = $this->parse(new StandardLLMClient(), "{\"choices\":[{\"finish_reason\":\"stop\",\"message\":{\"content\":\"done\"}}]}");
        $limited = $this->parse(new StandardLLMClient(), "{\"choices\":[{\"finish_reason\":\"length\",\"message\":{\"content\":\"partial\"}}]}");
        $refused = $this->parse(new StandardLLMClient(), "{\"choices\":[{\"finish_reason\":\"content_filter\",\"message\":{\"content\":null}}]}");

        $this->assertSame(LLMTurnStopReason::completed, $completed->stopReason);
        $this->assertSame(LLMTurnStopReason::outputLimit, $limited->stopReason);
        $this->assertSame(LLMTurnStopReason::refusal, $refused->stopReason);
    }

    #[Test]
    public function standardClientRejectsAnEmptySuccessfulResponse(): void
    {
        $this->expectException(LLMProviderException::class);

        $this->parse(new StandardLLMClient(), "{}");
    }

    #[Test]
    public function standardClientCallWithoutArgumentsYieldsAnEmptyDictionary(): void
    {
        $turn = $this->parse(new StandardLLMClient(), "{\"choices\":[{\"message\":{\"content\":null,\"tool_calls\":[{\"id\":\"call_1\",\"function\":{\"name\":\"probe_tool\",\"arguments\":\"{}\"}}]}}]}");

        $this->assertSame(1, $turn->toolCalls->count);
        $this->assertInstanceOf(Dictionary::class, $turn->toolCalls[0]->arguments);
        $this->assertTrue($turn->toolCalls[0]->arguments->isEmpty);
        $this->assertFalse($turn->isDone);
    }

    #[Test]
    public function standardClientPreservesObjectAndListArgumentShapes(): void
    {
        $turn = $this->parse(new StandardLLMClient(), "{\"choices\":[{\"message\":{\"tool_calls\":[{\"id\":\"call_1\",\"function\":{\"name\":\"probe_tool\",\"arguments\":\"{\\\"object\\\":{},\\\"list\\\":[]}\"}}]}}]}");
        $arguments = $turn->toolCalls[0]->arguments;

        $this->assertInstanceOf(Dictionary::class, $arguments["object"]);
        $this->assertInstanceOf(ArrayClass::class, $arguments["list"]);
    }

    #[Test]
    public function standardClientRejectsMalformedJSONToolArguments(): void
    {
        $this->expectException(LLMProviderException::class);
        $this->expectExceptionMessage("malformed JSON tool arguments");

        $this->parse(new StandardLLMClient(), "{\"choices\":[{\"message\":{\"tool_calls\":[{\"id\":\"call_1\",\"function\":{\"name\":\"probe_tool\",\"arguments\":\"{\"}}]}}]}");
    }

    #[Test]
    public function standardClientRejectsToolArgumentsThatDecodeAsAList(): void
    {
        $this->expectException(LLMProviderException::class);
        $this->expectExceptionMessage("tool arguments that are not an object");

        $this->parse(new StandardLLMClient(), "{\"choices\":[{\"message\":{\"tool_calls\":[{\"id\":\"call_1\",\"function\":{\"name\":\"probe_tool\",\"arguments\":\"[]\"}}]}}]}");
    }

    #[Test]
    public function standardClientRejectsAToolCallWithoutAnIdentifier(): void
    {
        $this->expectException(LLMProviderException::class);
        $this->expectExceptionMessage("without a non-empty id");

        $this->parse(new StandardLLMClient(), "{\"choices\":[{\"message\":{\"tool_calls\":[{\"id\":\"\",\"function\":{\"name\":\"probe_tool\",\"arguments\":\"{}\"}}]}}]}");
    }

    #[Test]
    public function anthropicKeepsEveryTextBlockRatherThanTheLastOne(): void
    {
        $turn = $this->parse(new AnthropicClient(), "{\"content\":[{\"type\":\"text\",\"text\":\"first. \"},{\"type\":\"tool_use\",\"id\":\"tu_1\",\"name\":\"probe_tool\",\"input\":{}},{\"type\":\"text\",\"text\":\"second.\"}],\"usage\":{\"input_tokens\":9,\"output_tokens\":3}}");

        $this->assertSame("first. second.", $turn->text);
        $this->assertSame(1, $turn->toolCalls->count);
        $this->assertSame(9, $turn->inputTokens);
        $this->assertSame(3, $turn->outputTokens);
    }

    #[Test]
    public function anthropicTurnWithoutATextBlockHasNoText(): void
    {
        $turn = $this->parse(new AnthropicClient(), "{\"content\":[{\"type\":\"tool_use\",\"id\":\"tu_1\",\"name\":\"probe_tool\",\"input\":{\"n\":1}}]}");

        $this->assertNull($turn->text);
        $this->assertSame(1, $turn->toolCalls->count);
    }

    #[Test]
    public function anthropicClientRejectsToolInputThatIsAList(): void
    {
        $this->expectException(LLMProviderException::class);
        $this->expectExceptionMessage("tool arguments that are not an object");

        $this->parse(new AnthropicClient(), "{\"content\":[{\"type\":\"tool_use\",\"id\":\"tu_1\",\"name\":\"probe_tool\",\"input\":[]}]}");
    }

    #[Test]
    public function anthropicClientRejectsAToolCallWithoutAName(): void
    {
        $this->expectException(LLMProviderException::class);
        $this->expectExceptionMessage("without a non-empty name");

        $this->parse(new AnthropicClient(), "{\"content\":[{\"type\":\"tool_use\",\"id\":\"tu_1\",\"input\":{}}]}");
    }

    #[Test]
    public function anthropicReportsTheProviderErrorBody(): void
    {
        $this->expectException(LLMProviderException::class);

        $this->parse(new AnthropicClient(), "{\"type\":\"error\",\"error\":{\"type\":\"overloaded_error\",\"message\":\"Overloaded\"}}");
    }

    #[Test]
    public function anthropicClientDistinguishesCompletionOutputLimitAndRefusal(): void
    {
        $completed = $this->parse(new AnthropicClient(), "{\"stop_reason\":\"end_turn\",\"content\":[{\"type\":\"text\",\"text\":\"done\"}]}");
        $limited = $this->parse(new AnthropicClient(), "{\"stop_reason\":\"max_tokens\",\"content\":[{\"type\":\"text\",\"text\":\"partial\"}]}");
        $refused = $this->parse(new AnthropicClient(), "{\"stop_reason\":\"refusal\",\"content\":[]}");

        $this->assertSame(LLMTurnStopReason::completed, $completed->stopReason);
        $this->assertSame(LLMTurnStopReason::outputLimit, $limited->stopReason);
        $this->assertSame(LLMTurnStopReason::refusal, $refused->stopReason);
    }

    #[Test]
    public function ollamaUsageIsCountedUnderItsOwnKeys(): void
    {
        $turn = $this->parse(new OllamaClient(), "{\"message\":{\"content\":\"hi\"},\"prompt_eval_count\":64,\"eval_count\":8}");

        $this->assertSame("hi", $turn->text);
        $this->assertSame(64, $turn->inputTokens);
        $this->assertSame(8, $turn->outputTokens);
    }

    #[Test]
    public function ollamaCallWithoutArgumentsYieldsAnEmptyDictionary(): void
    {
        $turn = $this->parse(new OllamaClient(), "{\"message\":{\"content\":null,\"tool_calls\":[{\"function\":{\"name\":\"probe_tool\"}}]}}");

        $this->assertSame(1, $turn->toolCalls->count);
        $this->assertSame("call_0", $turn->toolCalls[0]->id);
        $this->assertTrue($turn->toolCalls[0]->arguments->isEmpty);
    }

    #[Test]
    public function ollamaClientRejectsToolArgumentsThatAreAList(): void
    {
        $this->expectException(LLMProviderException::class);
        $this->expectExceptionMessage("tool arguments that are not an object");

        $this->parse(new OllamaClient(), "{\"message\":{\"tool_calls\":[{\"function\":{\"name\":\"probe_tool\",\"arguments\":[]}}]}}");
    }

    #[Test]
    public function ollamaClientRejectsAToolCallWithoutAName(): void
    {
        $this->expectException(LLMProviderException::class);
        $this->expectExceptionMessage("without a non-empty name");

        $this->parse(new OllamaClient(), "{\"message\":{\"tool_calls\":[{\"function\":{\"arguments\":{}}}]}}");
    }

    #[Test]
    public function ollamaReportsTheProviderErrorBody(): void
    {
        $this->expectException(LLMProviderException::class);

        $this->parse(new OllamaClient(), "{\"error\":\"model \\\"nope\\\" not found\"}");
    }

    #[Test]
    public function ollamaClientDistinguishesCompletionAndOutputLimit(): void
    {
        $completed = $this->parse(new OllamaClient(), "{\"done\":true,\"done_reason\":\"stop\",\"message\":{\"content\":\"done\"}}");
        $limited = $this->parse(new OllamaClient(), "{\"done\":true,\"done_reason\":\"length\",\"message\":{\"content\":\"partial\"}}");

        $this->assertSame(LLMTurnStopReason::completed, $completed->stopReason);
        $this->assertSame(LLMTurnStopReason::outputLimit, $limited->stopReason);
    }

    private function parse(LLMClient $client, string $json): LLMTurn
    {
        $body = Dictionary::dictionaryWithArray(json_decode($json) ?? [], false);
        /** @var LLMTurn */
        return new ReflectionMethod($client, "parse")->invoke($client, $body);
    }
}
