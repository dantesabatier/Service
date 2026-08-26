<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;
use Sabatier\Service\LLM\AnthropicClient;
use Sabatier\Service\LLM\LLMClient;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;
use Sabatier\Service\LLM\OllamaClient;
use Sabatier\Service\LLM\StandardLLMClient;

/** Covers what each client puts on the wire: the per-provider fields a caller adds through `extraBody`, and how a failed tool result survives a format with no flag for one. */
final class LLMClientBuildRequestTest extends TestCase
{
    #[Test]
    public function anthropicHonoursExtraBody(): void
    {
        $client = new AnthropicClient(endpoint: new URL("https://example.test/v1/messages"));
        $client->extraBody["temperature"] = 0.2;

        $body = $this->buildRequestBody($client);

        $this->assertSame(0.2, $body["temperature"]);
        $this->assertSame("user", $body["messages"][0]["role"]);
    }

    #[Test]
    public function extraBodyOverridesTheClientsOwnFieldOnCollision(): void
    {
        $client = new AnthropicClient(endpoint: new URL("https://example.test/v1/messages"));
        $client->extraBody["max_tokens"] = 64;

        $this->assertSame(64, $this->buildRequestBody($client)["max_tokens"]);
    }

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

    #[Test]
    public function aFailedToolResultIsMarkedForProvidersWithoutAFailureFlag(): void
    {
        $standard = new StandardLLMClient(endpoint: new URL("https://example.test/v1/chat/completions"));
        $ollama = new OllamaClient(endpoint: new URL("http://localhost:11434/api/chat"));
        $messages = new ArrayClass([new LLMMessage(LLMMessageRole::tool, "the query was malformed", toolCallId: "call_1", isError: true)]);

        $this->assertSame("Error: the query was malformed", $this->buildRequestBody($standard, $messages)["messages"][0]["content"]);
        $this->assertSame("Error: the query was malformed", $this->buildRequestBody($ollama, $messages)["messages"][0]["content"]);
    }

    #[Test]
    public function aSuccessfulToolResultIsLeftAlone(): void
    {
        $standard = new StandardLLMClient(endpoint: new URL("https://example.test/v1/chat/completions"));
        $messages = new ArrayClass([new LLMMessage(LLMMessageRole::tool, "42 rows", toolCallId: "call_1")]);

        $this->assertSame("42 rows", $this->buildRequestBody($standard, $messages)["messages"][0]["content"]);
    }

    #[Test]
    public function anthropicKeepsUsingItsOwnFailureFlag(): void
    {
        $client = new AnthropicClient(endpoint: new URL("https://example.test/v1/messages"));
        $messages = new ArrayClass([new LLMMessage(LLMMessageRole::tool, "the query was malformed", toolCallId: "tu_1", isError: true)]);

        $toolResult = $this->buildRequestBody($client, $messages)["messages"][0]["content"][0];

        $this->assertTrue($toolResult["is_error"]);
        $this->assertSame("the query was malformed", $toolResult["content"]);
    }

    /**
     * @param ArrayClass<LLMMessage>|null $messages
     * @return array<string, mixed>
     */
    private function buildRequestBody(LLMClient $client, ?ArrayClass $messages = null): array
    {
        $messages ??= new ArrayClass([new LLMMessage(LLMMessageRole::user, "hello")]);
        $request = new ReflectionMethod($client, "buildRequest")->invoke($client, $messages, new ArrayClass(), null);
        /** @var array<string, mixed> */
        return json_decode((string)$request->httpBody, true);
    }
}
