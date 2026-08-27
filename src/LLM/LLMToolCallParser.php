<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use JsonException;
use Sabatier\Foundation\Dictionary;
use stdClass;

/** Normalizes the provider-specific tool-call envelope without validating domain argument types. */
final class LLMToolCallParser
{
    /**
     * Parses a tool call whose arguments arrive as JSON text.
     *
     * @param mixed $id The provider-assigned call identifier.
     * @param mixed $name The requested tool name.
     * @param mixed $arguments The JSON-encoded argument object.
     * @throws LLMProviderException The call has no usable identity or its arguments are malformed.
     */
    public function parseEncoded(mixed $id, mixed $name, mixed $arguments): LLMToolCall
    {
        if (!is_string($arguments)) {
            throw new LLMProviderException("The LLM provider returned tool arguments that are not encoded as JSON.");
        }
        try {
            $decoded = json_decode($arguments, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new LLMProviderException("The LLM provider returned malformed JSON tool arguments: {$exception->getMessage()}");
        }
        if (!$decoded instanceof stdClass) {
            throw new LLMProviderException("The LLM provider returned tool arguments that are not an object.");
        }
        return $this->parseObject($id, $name, Dictionary::dictionaryWithArray($decoded, false));
    }

    /**
     * Parses a tool call whose arguments have already been decoded.
     *
     * @param mixed $id The provider-assigned call identifier.
     * @param mixed $name The requested tool name.
     * @param mixed $arguments The decoded argument object.
     * @throws LLMProviderException The call has no usable identity or its arguments are not an object.
     */
    public function parseObject(mixed $id, mixed $name, mixed $arguments): LLMToolCall
    {
        return new LLMToolCall($this->identity($id, "id"), $this->identity($name, "name"), $arguments instanceof Dictionary ? $arguments : throw new LLMProviderException("The LLM provider returned tool arguments that are not an object."));
    }

    private function identity(mixed $value, string $field): string
    {
        if (!is_string($value) || trim($value) === "") {
            throw new LLMProviderException("The LLM provider returned a tool call without a non-empty $field.");
        }
        return $value;
    }
}
