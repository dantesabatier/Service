<?php

// PHPUnit owns the exception boundary, including reflection into the provider's wire adapter.
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use JsonException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionMethod;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\URL;
use Sabatier\Service\LLM\AnthropicClient;
use Sabatier\Service\LLM\LLMAgent;
use Sabatier\Service\LLM\LLMClient;
use Sabatier\Service\LLM\LLMExecutionPolicy;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;
use Sabatier\Service\LLM\LLMModelTurnFinishedEvent;
use Sabatier\Service\LLM\LLMRunEvent;
use Sabatier\Service\LLM\LLMRunFinishedEvent;
use Sabatier\Service\LLM\LLMRunObserver;
use Sabatier\Service\LLM\LLMRunStopReason;
use Sabatier\Service\LLM\LLMToolCall;
use Sabatier\Service\LLM\LLMTurn;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use Sabatier\Service\MCP\Tools\ToolRegistry;

/** Exercises Anthropic cache markers and token accounting without contacting a provider. */
final class AnthropicPromptCachingTest extends TestCase
{
    #[Test]
    public function cachingIsOptIn(): void
    {
        $client = new AnthropicClient(endpoint: new URL("https://example.test/v1/messages"));

        $body = $this->buildRequestBody($client, $this->tools(), "system");

        $this->assertFalse($client->cachePromptPrefix);
        $this->assertSame("system", $body["system"]);
        $this->assertArrayNotHasKey("cache_control", $body);
        $this->assertArrayNotHasKey("cache_control", $body["tools"][0]);
        $this->assertArrayNotHasKey("cache_control", $body["tools"][1]);
    }

    #[Test]
    public function onlyTheLastToolAndSystemBlockAreMarked(): void
    {
        $client = new AnthropicClient(endpoint: new URL("https://example.test/v1/messages"));
        $client->cachePromptPrefix = true;
        $tools = $this->tools();
        $lastTool = $tools->last;
        $this->assertInstanceOf(ToolDescriptor::class, $lastTool);

        $body = $this->buildRequestBody($client, $tools, " system\n");

        $this->assertSame([[
            "type" => "text",
            "text" => " system\n",
            "cache_control" => ["type" => "ephemeral"],
        ]], $body["system"]);
        $this->assertSame(["first", "second"], array_column($body["tools"], "name"));
        $this->assertArrayNotHasKey("cache_control", $body["tools"][0]);
        $this->assertSame(["type" => "ephemeral"], $body["tools"][1]["cache_control"]);
        $this->assertSame($lastTool->inputSchema, $body["tools"][1]["input_schema"]);
        $this->assertSame([["role" => "user", "content" => "hello"]], $body["messages"]);
        $this->assertArrayNotHasKey("cache_control", $body);

        $client->cachePromptPrefix = false;
        $uncached = $this->buildRequestBody($client, $tools, " system\n");
        $this->assertSame(" system\n", $uncached["system"]);
        $this->assertArrayNotHasKey("cache_control", $uncached["tools"][1]);
        $this->assertArrayNotHasKey("cache_control", $lastTool->jsonSerialize());
    }

    #[Test]
    #[TestWith([null])]
    #[TestWith([""])]
    #[TestWith([" \n\t"])]
    public function absentOrBlankSystemTextDoesNotCreateACacheBlock(?string $systemPrompt): void
    {
        $client = new AnthropicClient(endpoint: new URL("https://example.test/v1/messages"));
        $client->cachePromptPrefix = true;

        $body = $this->buildRequestBody($client, $this->tools(), $systemPrompt);

        $this->assertSame($systemPrompt, $body["system"] ?? null);
        $this->assertSame($systemPrompt !== null, array_key_exists("system", $body));
        $this->assertSame(["type" => "ephemeral"], $body["tools"][1]["cache_control"]);
    }

    #[Test]
    #[TestWith([null])]
    #[TestWith(["system"])]
    public function emptyToolCataloguesStayEmpty(?string $systemPrompt): void
    {
        $client = new AnthropicClient(endpoint: new URL("https://example.test/v1/messages"));
        $client->cachePromptPrefix = true;

        $body = $this->buildRequestBody($client, new ArrayClass(), $systemPrompt);

        $this->assertSame([], $body["tools"]);
        $this->assertSame($systemPrompt === null ? null : [["type" => "text", "text" => "system", "cache_control" => ["type" => "ephemeral"]]], $body["system"] ?? null);
    }

    #[Test]
    public function explicitBodyFieldsKeepTheirOwnCachePolicy(): void
    {
        $client = new AnthropicClient(endpoint: new URL("https://example.test/v1/messages"));
        $client->cachePromptPrefix = true;
        $system = [["type" => "text", "text" => "custom", "cache_control" => ["type" => "ephemeral", "ttl" => "1h"]]];
        $tools = [["name" => "custom", "description" => "Custom tool", "input_schema" => ["type" => "object"]]];
        $client->extraBody = new Dictionary(["system" => $system, "tools" => $tools, "max_tokens" => 64]);

        $body = $this->buildRequestBody($client, $this->tools(), "ignored");

        $this->assertSame($system, $body["system"]);
        $this->assertSame($tools, $body["tools"]);
        $this->assertSame(64, $body["max_tokens"]);
        $this->assertSame($system, $client->extraBody["system"]);
        $this->assertSame($tools, $client->extraBody["tools"]);
    }

    #[Test]
    public function plainSystemAndEmptyToolOverridesAreNotRewritten(): void
    {
        $client = new AnthropicClient(endpoint: new URL("https://example.test/v1/messages"));
        $client->cachePromptPrefix = true;
        $client->extraBody = new Dictionary(["system" => "custom", "tools" => []]);

        $body = $this->buildRequestBody($client, $this->tools(), "ignored");

        $this->assertSame("custom", $body["system"]);
        $this->assertSame([], $body["tools"]);
    }

    #[Test]
    #[TestWith([false])]
    #[TestWith([true])]
    public function automaticCachingRemainsAnExplicitBodyOption(bool $cachePromptPrefix): void
    {
        $client = new AnthropicClient(endpoint: new URL("https://example.test/v1/messages"));
        $client->cachePromptPrefix = $cachePromptPrefix;
        $client->extraBody["cache_control"] = ["type" => "ephemeral"];

        $body = $this->buildRequestBody($client, $this->tools(), "system");

        $this->assertSame(["type" => "ephemeral"], $body["cache_control"]);
        $this->assertSame([["role" => "user", "content" => "hello"]], $body["messages"]);
    }

    /**
     * @param array<string, mixed> $usage
     * @param int $expectedInputTokens
     * @throws ReflectionException
     */
    #[Test]
    #[TestWith([["input_tokens" => 9, "output_tokens" => 3], 9])]
    #[TestWith([["input_tokens" => 9, "cache_creation_input_tokens" => 1000, "output_tokens" => 3], 1009])]
    #[TestWith([["input_tokens" => 9, "cache_read_input_tokens" => 2000, "output_tokens" => 3], 2009])]
    #[TestWith([["input_tokens" => 9, "cache_creation_input_tokens" => 1000, "cache_read_input_tokens" => 2000, "output_tokens" => 3], 3009])]
    #[TestWith([["input_tokens" => 0, "cache_creation_input_tokens" => 0, "cache_read_input_tokens" => 0, "output_tokens" => 3], 0])]
    #[TestWith([["cache_read_input_tokens" => 2000, "output_tokens" => 3], 2000])]
    #[TestWith([["input_tokens" => 9, "cache_creation_input_tokens" => 1000, "cache_creation" => ["ephemeral_5m_input_tokens" => 600, "ephemeral_1h_input_tokens" => 400], "output_tokens" => 3], 1009])]
    public function allInputCategoriesAreCountedExactlyOnce(array $usage, int $expectedInputTokens): void
    {
        $turn = $this->parseUsage($usage);

        $this->assertSame($expectedInputTokens, $turn->inputTokens);
        $this->assertSame(3, $turn->outputTokens);
        $this->assertSame("done", $turn->text);
    }

    #[Test]
    public function absentTokenCountersStillDefaultToZero(): void
    {
        $turn = $this->parseUsage([]);

        $this->assertSame(0, $turn->inputTokens);
        $this->assertSame(0, $turn->outputTokens);
    }

    #[Test]
    #[TestWith([false])]
    #[TestWith([true])]
    public function cacheMarkersDoNotAlterThinkingOrToolMessages(bool $withToolCall): void
    {
        $client = new AnthropicClient(endpoint: new URL("https://example.test/v1/messages"));
        $client->cachePromptPrefix = true;
        $thinking = new Dictionary(["type" => "thinking", "thinking" => "reasoning", "signature" => "signed"]);
        $calls = $withToolCall ? new ArrayClass([new LLMToolCall("call-1", "first", new Dictionary(["query" => "value"]))]) : null;
        $messages = new ArrayClass([
            new LLMMessage(LLMMessageRole::assistant, "answer", toolCalls: $calls, thinkingBlocks: new ArrayClass([$thinking])),
        ]);
        $expectedContent = [$thinking->array, ["type" => "text", "text" => "answer"]];
        if ($withToolCall) {
            $expectedContent[] = ["type" => "tool_use", "id" => "call-1", "name" => "first", "input" => ["query" => "value"]];
        }
        $expectedMessages = [["role" => "assistant", "content" => $expectedContent]];
        if ($withToolCall) {
            $messages->append(new LLMMessage(LLMMessageRole::tool, "result", toolCallId: "call-1"));
            $expectedMessages[] = ["role" => "user", "content" => [["type" => "tool_result", "tool_use_id" => "call-1", "content" => "result"]]];
        }

        $body = $this->buildRequestBody($client, $this->tools(), "system", $messages);

        $this->assertSame($expectedMessages, $body["messages"]);
        $this->assertSame(["type" => "thinking", "thinking" => "reasoning", "signature" => "signed"], $thinking->array);
    }

    #[Test]
    public function cacheMarkersDoNotAlterImageMessages(): void
    {
        $client = new AnthropicClient(endpoint: new URL("https://example.test/v1/messages"));
        $client->cachePromptPrefix = true;
        $images = new ArrayClass([new Dictionary(["mimeType" => "image/png", "data" => "encoded"])]);
        $messages = new ArrayClass([new LLMMessage(LLMMessageRole::user, "inspect", images: $images)]);

        $body = $this->buildRequestBody($client, $this->tools(), "system", $messages);

        $this->assertSame([["role" => "user", "content" => [
            ["type" => "image", "source" => ["type" => "base64", "media_type" => "image/png", "data" => "encoded"]],
            ["type" => "text", "text" => "inspect"],
        ]]], $body["messages"]);
    }

    #[Test]
    #[TestWith([1000, 1000, LLMRunStopReason::done])]
    #[TestWith([999, 1000, LLMRunStopReason::inputTokenLimit])]
    #[TestWith([1000, 999, LLMRunStopReason::totalTokenLimit])]
    public function cachedInputReachesRunBudgetsAndTraceEvents(int $maxInputTokens, int $maxTotalTokens, LLMRunStopReason $expectedStopReason): void
    {
        $turn = $this->parseUsage(["input_tokens" => 10, "cache_creation_input_tokens" => 90, "cache_read_input_tokens" => 900]);
        $client = $this->createMock(LLMClient::class);
        $client->expects($this->once())->method("complete")->willReturn($turn);
        /** @var ArrayClass<LLMRunEvent> $events */
        $events = new ArrayClass();
        $observer = $this->createMock(LLMRunObserver::class);
        $observer->expects($this->exactly(6))->method("observe")->willReturnCallback(function (LLMRunEvent $event) use ($events): void {
            $events->append($event);
        });
        $agent = new LLMAgent($client, new ToolRegistry(new ArrayClass()), canSpawnSubagents: false, executionPolicy: new LLMExecutionPolicy(maxInputTokens: $maxInputTokens, maxTotalTokens: $maxTotalTokens), observer: $observer);

        $run = $agent->run(new ArrayClass([new LLMMessage(LLMMessageRole::user, "task")]));

        $this->assertSame($expectedStopReason, $run->stopReason);
        $this->assertSame($expectedStopReason === LLMRunStopReason::done, $run->isComplete);
        $this->assertSame(1000, $run->inputTokens);
        $modelFinished = $events->first(fn(LLMRunEvent $event): bool => $event instanceof LLMModelTurnFinishedEvent);
        $runFinished = $events->last;
        $this->assertInstanceOf(LLMModelTurnFinishedEvent::class, $modelFinished);
        $this->assertInstanceOf(LLMRunFinishedEvent::class, $runFinished);
        $this->assertSame(1000, $modelFinished->inputTokens);
        $this->assertSame(1000, $runFinished->inputTokens);
    }

    /** @return ArrayClass<ToolDescriptor> */
    private function tools(): ArrayClass
    {
        return new ArrayClass([
            new ToolDescriptor("first", "First tool", ["type" => "object"]),
            new ToolDescriptor("second", "Second tool", ["type" => "object"]),
        ]);
    }

    /**
     * @param AnthropicClient $client
     * @param ArrayClass<ToolDescriptor> $tools
     * @param string|null $systemPrompt
     * @param ArrayClass<LLMMessage>|null $messages
     * @return array<string, mixed>
     * @throws JsonException
     * @throws ReflectionException
     */
    private function buildRequestBody(AnthropicClient $client, ArrayClass $tools, ?string $systemPrompt, ?ArrayClass $messages = null): array
    {
        $messages ??= new ArrayClass([new LLMMessage(LLMMessageRole::user, "hello")]);
        /** @var URLRequest $request */
        $request = new ReflectionMethod($client, "buildRequest")->invoke($client, $messages, $tools, $systemPrompt);
        return json_decode((string)$request->httpBody, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $usage
     * @throws ReflectionException
     */
    private function parseUsage(array $usage): LLMTurn
    {
        $body = Dictionary::dictionaryWithArray([
            "content" => [["type" => "text", "text" => "done"]],
            "stop_reason" => "end_turn",
            "usage" => (object)$usage,
        ], false);
        /** @var LLMTurn */
        return new ReflectionMethod(AnthropicClient::class, "parse")->invoke(new AnthropicClient(), $body);
    }
}
