<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Service\InternalServerErrorException;
use Sabatier\Service\LLM\AnthropicClient;
use Sabatier\Service\LLM\LLMClient;
use Sabatier\Service\LLM\LLMTurn;
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
        $turn = $this->parse(new StandardLLMClient(), '{"choices":[{"message":{"content":"hi"}}],"usage":{"prompt_tokens":137,"completion_tokens":42}}');

        $this->assertSame(137, $turn->inputTokens);
        $this->assertSame(42, $turn->outputTokens);
    }

    #[Test]
    public function responsesApiUsageIsStillCounted(): void
    {
        $turn = $this->parse(new StandardLLMClient(), '{"choices":[{"message":{"content":"hi"}}],"usage":{"input_tokens":11,"output_tokens":5}}');

        $this->assertSame(11, $turn->inputTokens);
        $this->assertSame(5, $turn->outputTokens);
    }

    #[Test]
    public function standardClientReportsTheProviderErrorBody(): void
    {
        $this->expectException(InternalInconsistencyException::class);

        $this->parse(new StandardLLMClient(), '{"error":{"message":"model not found","type":"invalid_request_error"}}');
    }

    #[Test]
    public function standardClientCallWithoutArgumentsYieldsAnEmptyDictionary(): void
    {
        $turn = $this->parse(new StandardLLMClient(), '{"choices":[{"message":{"content":null,"tool_calls":[{"id":"call_1","function":{"name":"probe_tool","arguments":"{}"}}]}}]}');

        $this->assertSame(1, $turn->toolCalls->count);
        $this->assertInstanceOf(Dictionary::class, $turn->toolCalls[0]->arguments);
        $this->assertTrue($turn->toolCalls[0]->arguments->isEmpty);
        $this->assertFalse($turn->isDone);
    }

    #[Test]
    public function anthropicKeepsEveryTextBlockRatherThanTheLastOne(): void
    {
        $turn = $this->parse(new AnthropicClient(), '{"content":[{"type":"text","text":"first. "},{"type":"tool_use","id":"tu_1","name":"probe_tool","input":{}},{"type":"text","text":"second."}],"usage":{"input_tokens":9,"output_tokens":3}}');

        $this->assertSame("first. second.", $turn->text);
        $this->assertSame(1, $turn->toolCalls->count);
        $this->assertSame(9, $turn->inputTokens);
        $this->assertSame(3, $turn->outputTokens);
    }

    #[Test]
    public function anthropicTurnWithoutATextBlockHasNoText(): void
    {
        $turn = $this->parse(new AnthropicClient(), '{"content":[{"type":"tool_use","id":"tu_1","name":"probe_tool","input":{"n":1}}]}');

        $this->assertNull($turn->text);
        $this->assertSame(1, $turn->toolCalls->count);
    }

    #[Test]
    public function anthropicReportsTheProviderErrorBody(): void
    {
        $this->expectException(InternalServerErrorException::class);

        $this->parse(new AnthropicClient(), '{"type":"error","error":{"type":"overloaded_error","message":"Overloaded"}}');
    }

    #[Test]
    public function ollamaUsageIsCountedUnderItsOwnKeys(): void
    {
        $turn = $this->parse(new OllamaClient(), '{"message":{"content":"hi"},"prompt_eval_count":64,"eval_count":8}');

        $this->assertSame("hi", $turn->text);
        $this->assertSame(64, $turn->inputTokens);
        $this->assertSame(8, $turn->outputTokens);
    }

    #[Test]
    public function ollamaCallWithoutArgumentsYieldsAnEmptyDictionary(): void
    {
        $turn = $this->parse(new OllamaClient(), '{"message":{"content":null,"tool_calls":[{"function":{"name":"probe_tool"}}]}}');

        $this->assertSame(1, $turn->toolCalls->count);
        $this->assertSame("call_0", $turn->toolCalls[0]->id);
        $this->assertTrue($turn->toolCalls[0]->arguments->isEmpty);
    }

    #[Test]
    public function ollamaReportsTheProviderErrorBody(): void
    {
        $this->expectException(InternalInconsistencyException::class);

        $this->parse(new OllamaClient(), '{"error":"model \"nope\" not found"}');
    }

    private function parse(LLMClient $client, string $json): LLMTurn
    {
        $body = Dictionary::dictionaryWithArray(json_decode($json) ?? [], false);
        /** @var LLMTurn */
        return new ReflectionMethod($client, "parse")->invoke($client, $body);
    }
}
