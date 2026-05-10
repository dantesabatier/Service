<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM\Providers;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\URL;
use Sabatier\Service\LLM\LLMClient;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;
use Sabatier\Service\LLM\LLMToolCall;
use Sabatier\Service\LLM\LLMTurn;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use stdClass;
use function Sabatier\Foundation\fatal_error;

/**
 * LLMClient implementation for OpenAI-compatible APIs.
 *
 * Formats messages and tools according to the OpenAI Chat Completions spec. The system prompt
 * is prepended as a `system` role message. Tool calls are expressed as `tool_calls` on the
 * assistant message and results as `tool` role messages. JSON schemas for tools are normalised
 * to satisfy OpenAI's requirement that array-typed properties declare an `items` field.
 * Auth is passed via the `Authorization: Bearer` header.
 */
final class StandardLLMClient extends LLMClient
{
    #[Override]
    public string $version = "2022-11-28";
    #[Override]
    public int $maxTokens = 8192;

    public function __construct(private readonly ?string $model = null, private readonly ?URL $endpoint = null, private readonly ?string $key = null)
    {
    }

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @param ArrayClass<ToolDescriptor> $tools
     */
    #[Override]
    protected function buildRequest(ArrayClass $messages, ArrayClass $tools, ?string $systemPrompt = null): URLRequest
    {
        $request = new URLRequest($this->endpoint ?? fatal_error("Endpoint URL must be provided for StandardLLMClient"));
        $request->httpMethod = HTTPRequestMethod::post;
        $request->allHTTPHeaderFields = new Dictionary([
            "Authorization" => "Bearer $this->key",
            "Content-Type" => "application/json",
        ]);
        $formattedMessages = $this->formatMessages($messages);
        if ($systemPrompt !== null) {
            array_unshift($formattedMessages, ["role" => "system", "content" => $systemPrompt]);
        }
        $body = [
            "model" => $this->model,
            "max_tokens" => $this->maxTokens,
            "messages" => $formattedMessages,
        ];
        if (!$tools->isEmpty) {
            $body["tools"] = $this->formatTools($tools);
        }
        $request->httpBody = (string)json_encode($body);
        return $request;
    }

    /**
     * @param ArrayClass<LLMMessage> $messages
     * @return list<array<string, mixed>>
     */
    private function formatMessages(ArrayClass $messages): array
    {
        $result = [];
        foreach ($messages as $message) {
            if ($message->role === LLMMessageRole::tool) {
                $result[] = [
                    "role" => "tool",
                    "tool_call_id" => $message->toolCallId ?? "",
                    "content" => $message->content ?? "",
                ];
                continue;
            }
            $toolCalls = $message->toolCalls;
            if ($toolCalls && !$toolCalls->isEmpty && $message->role === LLMMessageRole::assistant) {
                $calls = [];
                foreach ($toolCalls as $call) {
                    $calls[] = [
                        "id" => $call->id,
                        "type" => "function",
                        "function" => [
                            "name" => $call->name,
                            "arguments" => (string)json_encode($call->arguments->array),
                        ],
                    ];
                }
                $entry = [
                    "role" => "assistant",
                    "content" => $message->content,
                    "tool_calls" => $calls,
                ];
                if ($message->reasoningContent !== null) {
                    $entry["reasoning_content"] = $message->reasoningContent;
                }
                $result[] = $entry;
            } else {
                $entry = [
                    "role" => $message->role,
                    "content" => $message->content ?? "",
                ];
                if ($message->reasoningContent !== null && $message->role === LLMMessageRole::assistant) {
                    $entry["reasoning_content"] = $message->reasoningContent;
                }
                $result[] = $entry;
            }
        }
        return $result;
    }

    /**
     * @param ArrayClass<ToolDescriptor> $tools
     * @return list<array<string, mixed>>
     */
    private function formatTools(ArrayClass $tools): array
    {
        $result = [];
        foreach ($tools as $tool) {
            $result[] = [
                "type" => "function",
                "function" => [
                    "name" => $tool->name,
                    "description" => $tool->description,
                    "parameters" => $this->normalizeSchema($tool->inputSchema),
                ],
            ];
        }
        return $result;
    }

    /**
     * OpenAI requires array-typed properties to have an `items` field.
     * Recursively adds `items: {}` where missing.
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function normalizeSchema(array $schema): array
    {
        if (($schema["type"] ?? null) === "array" && !array_key_exists("items", $schema)) {
            $schema["items"] = new stdClass();
        }
        if (isset($schema["properties"]) && is_array($schema["properties"])) {
            foreach ($schema["properties"] as $key => $prop) {
                if (is_array($prop)) {
                    $schema["properties"][$key] = $this->normalizeSchema($prop);
                }
            }
        }
        return $schema;
    }

    #[Override]
    protected function parse(Dictionary $body): LLMTurn
    {
        /** @var Dictionary<mixed>|null $error */
        $error = $body["error"];
        !$error instanceof Dictionary ?: fatal_error($error["message"] ?? "Unknown API error");
        $text = null;
        $reasoningContent = null;
        /** @var ArrayClass<LLMToolCall> $toolCalls */
        $toolCalls = new ArrayClass();
        /** @var ArrayClass<Dictionary<mixed>> $choices */
        $choices = $body["choices"] ?? new ArrayClass();
        foreach ($choices as $choice) {
            /** @var Dictionary<mixed> $message */
            $message = $choice["message"] ?? new Dictionary();
            $text = $message["content"];
            /** @var string|null $reasoningContent */
            $reasoningContent = $message["reasoning_content"];
            /** @var ArrayClass<Dictionary<mixed>> $calls */
            $calls = $message["tool_calls"] ?? new ArrayClass();
            foreach ($calls as $tc) {
                /** @var Dictionary<mixed> $fn */
                $fn = $tc["function"] ?? new Dictionary();
                $id = $tc["id"] ?? "";
                $name = $fn["name"] ?? "";
                $arguments = Dictionary::dictionaryWithArray(json_decode($fn["arguments"] ?? "[]", true) ?? [], false);
                $toolCalls->append(new LLMToolCall($id, $name, $arguments));
            }
            break;
        }
        /** @var Dictionary<int<0, max>> $usage */
        $usage = $body["usage"] ?? new Dictionary();
        /** @var int<0, max> $inputTokens */
        $inputTokens = (int)($usage["input_tokens"] ?? 0);
        /** @var int<0, max> $outputTokens */
        $outputTokens = (int)($usage["output_tokens"] ?? 0);
        return new LLMTurn($text, $toolCalls, $inputTokens, $outputTokens, reasoningContent: $reasoningContent);
    }
}
