<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UserDefaults;
use Sabatier\Service\LLM\Providers\AnthropicClient;
use Sabatier\Service\LLM\Providers\StandardLLMClient;
use const Sabatier\Service\LLMProvidersPreferencesKey;

/**
 * Configuration for an LLM service provider.
 *
 * Holds the endpoint URL, API key, and the list of models the provider exposes.
 * Acts as a factory for the appropriate `LLMClient` implementation via `client()`.
 * Providers are persisted in `UserDefaults` under `LLMProvidersPreferencesKey`; `all()` reads
 * that list and falls back to the built-in Anthropic default when nothing is stored.
 */
final class LLMProvider
{
    public Dictionary $dictionaryRepresentation {
        get => new Dictionary([
            "name" => $this->name,
            "identifier" => $this->identifier,
            "url" => $this->url,
            "apiKey" => $this->apiKey,
            "models" => $this->models->map(fn(LLMModel $model): Dictionary => $model->dictionaryRepresentation),
        ]);
    }

    /** @param ArrayClass<LLMModel> $models */
    public function __construct(public readonly string $name, public readonly string $identifier, public readonly URL $url, public readonly string $apiKey, public readonly ArrayClass $models)
    {
    }

    public function client(?string $model = null): LLMClient
    {
        return match ($this->identifier) {
            "anthropic" => new AnthropicClient($model, $this->url, $this->apiKey),
            default => new StandardLLMClient($model, $this->url, $this->apiKey),
        };
    }

    /**
     * @return ArrayClass<LLMProvider>
     */
    public static function all(): ArrayClass
    {
        if ($stored = UserDefaults::standard()->array(LLMProvidersPreferencesKey)) {
            $providers = $stored->map(fn(Dictionary $dictionary): LLMProvider => LLMProviderBuilder::build($dictionary));
            if (!$providers->isEmpty) {
                return $providers;
            }
        }
        return self::defaults();
    }

    public static function find(string $identifier): ?self
    {
        return self::all()->first(fn(LLMProvider $provider) => $provider->identifier === $identifier);
    }

    /** @return ArrayClass<self> */
    private static function defaults(): ArrayClass
    {
        return new ArrayClass([self::anthropic()]);
    }

    public static function anthropic(): self
    {
        return new self(
            name: "Anthropic",
            identifier: "anthropic",
            url: new URL("https://api.anthropic.com/v1/messages"),
            apiKey: "",
            models: new ArrayClass([new LLMModel("Opus 4.7", "claude-opus-4-7", "opus"), new LLMModel("Sonnet 4.6", "claude-sonnet-4-6", "sonnet"), new LLMModel("Haiku 4.5", "claude-haiku-4-5-20251001", "haiku")])
        );
    }
}
