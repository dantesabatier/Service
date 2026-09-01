<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use JsonException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionMethod;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;
use Sabatier\Service\LLM\AnthropicClient;
use Sabatier\Service\LLM\LLMClient;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;
use Sabatier\Service\LLM\LLMToolCall;
use Sabatier\Service\LLM\OllamaClient;
use Sabatier\Service\LLM\StandardLLMClient;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use stdClass;

/** Covers what each client puts on the wire: the per-provider fields a caller adds through `extraBody`, and how a failed tool result survives a format with no flag for one. */
final class LLMClientBuildRequestTest extends TestCase
{
    /** @throws ReflectionException */
    #[Test]
    public function mcpAnnotationsAreNotSentToModelProviders(): void
    {
        $tools = new ArrayClass([new ToolDescriptor("lookup", "Look up a record.", ["type" => "object"], annotations: ["readOnlyHint" => true])]);
        $endpoint = new URL("https://example.test/api");
        foreach ([new AnthropicClient(endpoint: $endpoint), new StandardLLMClient(endpoint: $endpoint), new OllamaClient(endpoint: $endpoint)] as $client) {
            $body = $this->buildRequestBody($client, tools: $tools);
            $tool = $body["tools"][0];
            $this->assertArrayNotHasKey("annotations", $tool);
            $this->assertArrayNotHasKey("annotations", $tool["function"] ?? $tool);
        }
    }

    /** @throws ReflectionException */
    #[Test]
    public function anthropicHonoursExtraBody(): void
    {
        $client = new AnthropicClient(endpoint: new URL("https://example.test/v1/messages"));
        $client->extraBody["temperature"] = 0.2;

        $body = $this->buildRequestBody($client);

        $this->assertSame(0.2, $body["temperature"]);
        $this->assertSame("user", $body["messages"][0]["role"]);
    }

    /**
     * @throws JsonException
     * @throws ReflectionException
     */
    #[Test]
    public function extraBodyOverridesTheClientsOwnFieldOnCollision(): void
    {
        $client = new AnthropicClient(endpoint: new URL("https://example.test/v1/messages"));
        $client->extraBody["max_tokens"] = 64;

        $this->assertSame(64, $this->buildRequestBody($client)["max_tokens"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function everyClientHonoursExtraBody(): void
    {
        $anthropic = new AnthropicClient(endpoint: new URL("https://example.test/v1/messages"));
        $standard = new StandardLLMClient(endpoint: new URL("https://example.test/v1/chat/completions"));
        $ollama = new OllamaClient(endpoint: new URL("http://localhost:11434/api/chat"));
        $anthropic->extraBody["seed"] = 7;
        $standard->extraBody["seed"] = 7;
        $ollama->extraBody["seed"] = 7;

        $this->assertSame(7, $this->buildRequestBody($anthropic)["seed"]);
        $this->assertSame(7, $this->buildRequestBody($standard)["seed"]);
        $this->assertSame(7, $this->buildRequestBody($ollama)["options"]["seed"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aFailedToolResultIsMarkedForProvidersWithoutAFailureFlag(): void
    {
        $standard = new StandardLLMClient(endpoint: new URL("https://example.test/v1/chat/completions"));
        $ollama = new OllamaClient(endpoint: new URL("http://localhost:11434/api/chat"));
        $messages = new ArrayClass([new LLMMessage(LLMMessageRole::tool, "the query was malformed", toolCallId: "call_1", isError: true)]);

        $this->assertSame("Error: the query was malformed", $this->buildRequestBody($standard, $messages)["messages"][0]["content"]);
        $this->assertSame("Error: the query was malformed", $this->buildRequestBody($ollama, $messages)["messages"][0]["content"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aSuccessfulToolResultIsLeftAlone(): void
    {
        $standard = new StandardLLMClient(endpoint: new URL("https://example.test/v1/chat/completions"));
        $messages = new ArrayClass([new LLMMessage(LLMMessageRole::tool, "42 rows", toolCallId: "call_1")]);

        $this->assertSame("42 rows", $this->buildRequestBody($standard, $messages)["messages"][0]["content"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anthropicKeepsUsingItsOwnFailureFlag(): void
    {
        $client = new AnthropicClient(endpoint: new URL("https://example.test/v1/messages"));
        $messages = new ArrayClass([new LLMMessage(LLMMessageRole::tool, "the query was malformed", toolCallId: "tu_1", isError: true)]);

        $toolResult = $this->buildRequestBody($client, $messages)["messages"][0]["content"][0];

        $this->assertTrue($toolResult["is_error"]);
        $this->assertSame("the query was malformed", $toolResult["content"]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function toolArgumentObjectsAndListsKeepTheirWireShapeAcrossProviders(): void
    {
        $calls = new ArrayClass([
            new LLMToolCall("empty", "probe", new Dictionary()),
            new LLMToolCall("nested", "probe", new Dictionary(["object" => new Dictionary(), "list" => new ArrayClass()])),
        ]);
        $messages = new ArrayClass([new LLMMessage(LLMMessageRole::assistant, null, $calls)]);

        $anthropic = $this->buildRequestObject(new AnthropicClient(endpoint: new URL("https://example.test/v1/messages")), $messages);
        $this->assertArgumentShapes($anthropic->messages[0]->content[0]->input, $anthropic->messages[0]->content[1]->input);

        $standard = $this->buildRequestObject(new StandardLLMClient(endpoint: new URL("https://example.test/v1/chat/completions")), $messages);
        $this->assertArgumentShapes(json_decode($standard->messages[0]->tool_calls[0]->function->arguments, flags: JSON_THROW_ON_ERROR), json_decode($standard->messages[0]->tool_calls[1]->function->arguments, flags: JSON_THROW_ON_ERROR));

        $ollama = $this->buildRequestObject(new OllamaClient(endpoint: new URL("http://localhost:11434/api/chat")), $messages);
        $this->assertArgumentShapes($ollama->messages[0]->tool_calls[0]->function->arguments, $ollama->messages[0]->tool_calls[1]->function->arguments);
    }

    private function assertArgumentShapes(mixed $empty, mixed $nested): void
    {
        $this->assertInstanceOf(stdClass::class, $empty);
        $this->assertSame([], get_object_vars($empty));
        $this->assertInstanceOf(stdClass::class, $nested);
        $this->assertInstanceOf(stdClass::class, $nested->object);
        $this->assertSame([], $nested->list);
    }

    /**
     * @param LLMClient $client The provider whose request is inspected without sending it.
     * @param ArrayClass<LLMMessage>|null $messages The messages to format, or a minimal user message.
     * @param ArrayClass<ToolDescriptor> $tools The catalogue to format for the provider.
     * @return array<string, mixed>
     * @throws ReflectionException
     */
    private function buildRequestBody(LLMClient $client, ?ArrayClass $messages = null, ArrayClass $tools = new ArrayClass()): array
    {
        $messages ??= new ArrayClass([new LLMMessage(LLMMessageRole::user, "hello")]);
        $request = new ReflectionMethod($client, "buildRequest")->invoke($client, $messages, $tools, null);
        /** @var array<string, mixed> */
        return json_decode((string)$request->httpBody, true);
    }

    /**
     * @param LLMClient $client The provider whose JSON object is inspected without losing empty object/list distinctions.
     * @param ArrayClass<LLMMessage> $messages The messages to format.
     * @throws JsonException
     * @throws ReflectionException
     */
    private function buildRequestObject(LLMClient $client, ArrayClass $messages): stdClass
    {
        $request = new ReflectionMethod($client, "buildRequest")->invoke($client, $messages, new ArrayClass(), null);
        /** @var stdClass */
        return json_decode((string)$request->httpBody, flags: JSON_THROW_ON_ERROR);
    }
}
