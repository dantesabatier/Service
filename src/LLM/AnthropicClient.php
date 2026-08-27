<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Service\MCP\Response\ToolDescriptor;
use function Sabatier\Foundation\fatal_error;

/**
 * LLMClient implementation for the Anthropic Messages API.
 *
 * Formats messages using Anthropic's content-block structure: tool results are batched into a
 * single `user` turn, assistant tool use is expressed as `tool_use` blocks, and images are
 * sent as base64-encoded `image` blocks. The system prompt is a top-level body field rather
 * than a message. API version and auth are passed via `anthropic-version` and `x-api-key` headers.
 *
 * A response may carry several `text` blocks — interleaved with `thinking` or `tool_use` ones — and all of them are the model's answer, so they are concatenated rather than overwritten. The turn's text stays `null` when no `text` block arrives at all, which is not the same as an empty one.
 */
final class AnthropicClient extends LLMClient
{
    #[Override]
    public string $version = "2023-06-01";
    #[Override]
    public int $maxTokens = 8192;

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
        $request->httpBody = (string)json_encode([...$body, ...$this->extraBody->array]);
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
                $toolResult = [
                    "type" => "tool_result",
                    "tool_use_id" => $message->toolCallId ?? "",
                    "content" => $message->content ?? "",
                ];
                if ($message->isError) {
                    $toolResult["is_error"] = true;
                }
                $pendingToolResults[] = $toolResult;
                continue;
            }
            if ($pendingToolResults !== []) {
                $result[] = ["role" => "user", "content" => $pendingToolResults];
                $pendingToolResults = [];
            }
            $toolCalls = $message->toolCalls;
            if ($toolCalls && !$toolCalls->isEmpty && $message->role === LLMMessageRole::assistant) {
                $content = [];
                if ($message->thinkingBlocks && !$message->thinkingBlocks->isEmpty) {
                    foreach ($message->thinkingBlocks as $tb) {
                        $content[] = $tb;
                    }
                }
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
            } elseif ($message->thinkingBlocks && !$message->thinkingBlocks->isEmpty && $message->role === LLMMessageRole::assistant) {
                $content = [];
                foreach ($message->thinkingBlocks as $tb) {
                    $content[] = $tb;
                }
                if ($message->content !== null && $message->content !== "") {
                    $content[] = ["type" => "text", "text" => $message->content];
                }
                $result[] = ["role" => "assistant", "content" => $content];
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
            throw new LLMProviderException($error["message"] ?? "Unknown API error");
        }
        $text = null;
        /** @var ArrayClass<LLMToolCall> $toolCalls */
        $toolCalls = new ArrayClass();
        /** @var ArrayClass<Dictionary<string>> $thinkingBlocks */
        $thinkingBlocks = new ArrayClass();
        /** @var ArrayClass<Dictionary<mixed>> $content */
        $content = $body["content"] ?? new ArrayClass();
        foreach ($content as $block) {
            match ($block["type"]) {
                "text" => $text = ($text ?? "") . (string)$block["text"],
                "tool_use" => $toolCalls->append(new LLMToolCall($block["id"] ?? "", $block["name"] ?? "", $block["input"] ?? new Dictionary())),
                "thinking" => $thinkingBlocks->append(new Dictionary(["type" => "thinking", "thinking" => (string)($block["thinking"] ?? ""), "signature" => (string)($block["signature"] ?? "")])),
                default => null,
            };
        }
        /** @var Dictionary<int<0, max>> $usage */
        $usage = $body["usage"] ?? new Dictionary();
        /** @var int<0, max> $inputTokens */
        $inputTokens = (int)($usage["input_tokens"] ?? 0);
        /** @var int<0, max> $outputTokens */
        $outputTokens = (int)($usage["output_tokens"] ?? 0);
        $finishReason = is_string($body["stop_reason"]) ? $body["stop_reason"] : null;
        return new LLMTurn($text, $toolCalls, $inputTokens, $outputTokens, $thinkingBlocks->isEmpty ? null : $thinkingBlocks, stopReason: self::stopReason($finishReason, $text, $toolCalls));
    }

    /** @param ArrayClass<LLMToolCall> $toolCalls */
    private static function stopReason(?string $finishReason, ?string $text, ArrayClass $toolCalls): LLMTurnStopReason
    {
        if (!$toolCalls->isEmpty) {
            return LLMTurnStopReason::toolUse;
        }
        return match ($finishReason) {
            "max_tokens", "pause_turn" => LLMTurnStopReason::outputLimit,
            "refusal" => LLMTurnStopReason::refusal,
            "end_turn", "stop_sequence" => $text !== null ? LLMTurnStopReason::completed : throw new LLMProviderException("The LLM provider ended the turn without a message.", true),
            null => $text !== null ? LLMTurnStopReason::completed : throw new LLMProviderException("The LLM provider returned a successful response without content.", true),
            default => throw new LLMProviderException("The LLM provider returned an unsupported stop reason: $finishReason"),
        };
    }
}
