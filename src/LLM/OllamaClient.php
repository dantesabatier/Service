<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\URL;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use function Sabatier\Foundation\fatal_error;

/**
 * LLMClient implementation for Ollama's native `/api/chat` endpoint.
 *
 * Ollama also exposes an OpenAI-compatible endpoint, served by {@see StandardLLMClient}, but that
 * one silently drops the runner options — most importantly `num_ctx`, the context window. The
 * native API is the only one that honours them, and it does so through a nested `options` object
 * rather than root-level fields, so the provider's extra body is sent there.
 *
 * The wire shape differs from OpenAI in ways this client absorbs so the agent loop sees the same
 * `LLMTurn` either way: the reply is a single `message` (not `choices[]`); tool-call arguments
 * arrive as an object, not a JSON string; native tool calls carry no id, so one is synthesised per
 * call for the loop to pair a result with; and token counts come as `prompt_eval_count` /
 * `eval_count`.
 */
final class OllamaClient extends LLMClient
{
    #[Override]
    public string $version = "";
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
        $request = new URLRequest($this->endpoint ?? fatal_error("Endpoint URL must be provided for OllamaClient"));
        $request->httpMethod = HTTPRequestMethod::post;
        $headers = new Dictionary(["Content-Type" => "application/json"]);
        if ($this->key !== null && $this->key !== "") {
            $headers["Authorization"] = "Bearer $this->key";
        }
        $request->allHTTPHeaderFields = $headers;
        $formattedMessages = $this->formatMessages($messages);
        if ($systemPrompt !== null) {
            array_unshift($formattedMessages, ["role" => "system", "content" => $systemPrompt]);
        }
        $body = [
            "model" => $this->model,
            "messages" => $formattedMessages,
            "stream" => false,
            // The native API groups runner parameters under `options`: the output cap (num_predict)
            // and the provider settings, which may override it.
            "options" => [...["num_predict" => $this->maxTokens], ...$this->extraBody->array],
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
                // Ollama pairs a result with its call by position, not by id, so the message's
                // synthetic tool_call_id is not sent: only the content goes.
                $result[] = [
                    "role" => "tool",
                    "content" => $message->content ?? "",
                ];
                continue;
            }
            $toolCalls = $message->toolCalls;
            if ($toolCalls && !$toolCalls->isEmpty && $message->role === LLMMessageRole::assistant) {
                $calls = [];
                foreach ($toolCalls as $call) {
                    $calls[] = [
                        "function" => [
                            "name" => $call->name,
                            "arguments" => $call->arguments->array,
                        ],
                    ];
                }
                $result[] = [
                    "role" => "assistant",
                    "content" => $message->content ?? "",
                    "tool_calls" => $calls,
                ];
                continue;
            }
            $entry = [
                "role" => $message->role,
                "content" => $message->content ?? "",
            ];
            if ($message->images && !$message->images->isEmpty) {
                // Ollama takes images as bare base64 strings on the message, without the data-URI prefix.
                $entry["images"] = $message->images->compactMap(fn(Dictionary $image): ?string => $image["data"])->array;
            }
            $result[] = $entry;
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
                    "parameters" => $tool->inputSchema,
                ],
            ];
        }
        return $result;
    }

    #[Override]
    protected function parse(Dictionary $body): LLMTurn
    {
        /** @var string|null $error */
        $error = $body["error"];
        $error === null ?: fatal_error(is_string($error) ? $error : "Unknown API error");
        /** @var Dictionary<mixed> $message */
        $message = $body["message"] ?? new Dictionary();
        /** @var string|null $text */
        $text = $message["content"];
        /** @var ArrayClass<LLMToolCall> $toolCalls */
        $toolCalls = new ArrayClass();
        /** @var ArrayClass<Dictionary<mixed>> $calls */
        $calls = $message["tool_calls"] ?? new ArrayClass();
        $index = 0;
        foreach ($calls as $tc) {
            /** @var Dictionary<mixed> $fn */
            $fn = $tc["function"] ?? new Dictionary();
            $name = $fn["name"] ?? "";
            // Ollama does not number the calls; the synthetic id only lets the agent loop pair this
            // result with its call. `arguments` already arrives as an object, not a string.
            $id = "call_" . $index++;
            $arguments = $fn["arguments"] instanceof Dictionary ? $fn["arguments"] : Dictionary::dictionaryWithArray((array)($fn["arguments"] ?? []), false);
            $toolCalls->append(new LLMToolCall($id, $name, $arguments));
        }
        /** @var int<0, max> $inputTokens */
        $inputTokens = (int)($body["prompt_eval_count"] ?? 0);
        /** @var int<0, max> $outputTokens */
        $outputTokens = (int)($body["eval_count"] ?? 0);
        return new LLMTurn($text, $toolCalls, $inputTokens, $outputTokens);
    }
}
