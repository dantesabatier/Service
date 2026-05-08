<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM\Providers;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\URL;
use Sabatier\Service\InternalServerErrorException;
use Sabatier\Service\LLM\LLMClient;
use Sabatier\Service\LLM\LLMMessage;
use Sabatier\Service\LLM\LLMMessageRole;
use Sabatier\Service\LLM\LLMToolCall;
use Sabatier\Service\LLM\LLMTurn;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use function Sabatier\Foundation\fatal_error;

/**
 * LLMClient implementation for the Anthropic Messages API.
 *
 * Formats messages using Anthropic's content-block structure: tool results are batched into a
 * single `user` turn, assistant tool use is expressed as `tool_use` blocks, and images are
 * sent as base64-encoded `image` blocks. The system prompt is a top-level body field rather
 * than a message. API version and auth are passed via `anthropic-version` and `x-api-key` headers.
 */
final class AnthropicClient extends LLMClient
{
    #[Override]
    public string $version = "2023-06-01";
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
        $request = new URLRequest($this->endpoint ?? fatal_error("Endpoint URL must be provided for AnthropicClient"));
        $request->httpMethod = HTTPRequestMethod::post;
        $request->allHTTPHeaderFields = new Dictionary([
            "x-api-key" => $this->key,
            "anthropic-version" => $this->version,
            "Content-Type" => "application/json",
        ]);
        $body = [
            "model" => $this->model,
            "max_tokens" => $this->maxTokens,
            "messages" => $this->formatMessages($messages),
            "tools" => $this->formatTools($tools),
        ];
        if ($systemPrompt !== null) {
            $body["system"] = $systemPrompt;
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
        $pendingToolResults = [];
        foreach ($messages as $message) {
            if ($message->role === LLMMessageRole::tool) {
                $pendingToolResults[] = [
                    "type" => "tool_result",
                    "tool_use_id" => $message->toolCallId ?? "",
                    "content" => $message->content ?? "",
                ];
                continue;
            }
            if ($pendingToolResults !== []) {
                $result[] = ["role" => "user", "content" => $pendingToolResults];
                $pendingToolResults = [];
            }
            $toolCalls = $message->toolCalls;
            if ($toolCalls && !$toolCalls->isEmpty && $message->role === LLMMessageRole::assistant) {
                $content = [];
                if ($message->content !== null && $message->content !== "") {
                    $content[] = ["type" => "text", "text" => $message->content];
                }
                foreach ($toolCalls as $call) {
                    $content[] = [
                        "type" => "tool_use",
                        "id" => $call->id,
                        "name" => $call->name,
                        "input" => $call->arguments->array,
                    ];
                }
                $result[] = ["role" => "assistant", "content" => $content];
            } elseif ($message->images && !$message->images->isEmpty) {
                $content = [];
                foreach ($message->images as $image) {
                    $content[] = [
                        "type" => "image",
                        "source" => [
                            "type" => "base64",
                            "media_type" => $image["mimeType"],
                            "data" => $image["data"],
                        ],
                    ];
                }
                if ($message->content !== null && $message->content !== "") {
                    $content[] = ["type" => "text", "text" => $message->content];
                }
                $result[] = ["role" => $message->role, "content" => $content];
            } else {
                $result[] = ["role" => $message->role, "content" => $message->content ?? ""];
            }
        }
        if ($pendingToolResults !== []) {
            $result[] = ["role" => "user", "content" => $pendingToolResults];
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
                "name" => $tool->name,
                "description" => $tool->description,
                "input_schema" => $tool->inputSchema,
            ];
        }
        return $result;
    }

    #[Override]
    protected function parse(Dictionary $body): LLMTurn
    {
        if ($body["type"] === "error") {
            /** @var Dictionary<mixed> $error */
            $error = $body["error"] ?? new Dictionary();
            throw new InternalServerErrorException($error["message"] ?? "Unknown API error");
        }
        $text = null;
        /** @var ArrayClass<LLMToolCall> $toolCalls */
        $toolCalls = new ArrayClass();
        /** @var ArrayClass<Dictionary<mixed>> $content */
        $content = $body["content"] ?? new ArrayClass();
        foreach ($content as $block) {
            match ($block["type"]) {
                "text" => $text = $block["text"],
                "tool_use" => $toolCalls->append(new LLMToolCall($block["id"] ?? "", $block["name"] ?? "", $block["input"] ?? new Dictionary())),
                default => null,
            };
        }
        /** @var Dictionary<int<0, max>> $usage */
        $usage = $body["usage"] ?? new Dictionary();
        /** @var int<0, max> $inputTokens */
        $inputTokens = (int)($usage["input_tokens"] ?? 0);
        /** @var int<0, max> $outputTokens */
        $outputTokens = (int)($usage["output_tokens"] ?? 0);
        return new LLMTurn($text, $toolCalls, $inputTokens, $outputTokens);
    }
}
