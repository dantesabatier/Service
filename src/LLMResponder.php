<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Service\MCP\Schema\AttributeSchemaFactory;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\Schema\ModelSchemaExtractor;
use Sabatier\Service\MCP\Schema\PredicateGuideFactory;
use Sabatier\Service\MCP\Schema\SchemaLocalizer;
use Sabatier\Service\MCP\Schema\VocabularyRepository;
use Sabatier\Service\MCP\ToolResolver;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Throwable;

/**
 * Abstract base for responders that drive an LLM agent over HTTP.
 *
 * Exposes the shared machinery every agent-facing endpoint needs: the tool
 * catalogue resolved from the managed object model (`$registry`) and the
 * schemas that describe it (`$descriptor`). Subclasses own their system
 * prompts, orchestration and persistence; they are POST-only by default.
 *
 * `callTool()` is the raw tool-call surface: it invokes one registry tool by
 * name and returns its JSON. Every concrete responder inherits it, so `/callTool`
 * matches the first LLM responder in the chain — `FirstResponderResolver` awards a
 * path to the first match, and the behavior is identical across subclasses as
 * long as `$restrictedTools` is unchanged. Override `callTool()` to give a
 * subclass its own route.
 */
abstract class LLMResponder extends Responder
{
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::post]);
    }
    protected ModelDescriptor $descriptor {
        get => $this->descriptor ??= new ModelDescriptor(new ModelSchemaExtractor($this->managedObjectContext, new AttributeSchemaFactory()), new VocabularyRepository(), new SchemaLocalizer(), new PredicateGuideFactory());
    }
    protected ToolRegistry $registry {
        get => $this->registry ??= new ToolRegistry(new ToolResolver($this->managedObjectContext, $this->descriptor)->resolve());
    }
    /** @var ArrayClass<string> Tools refused by the raw tool-call surface even when present in the registry. Empty by default — the whole catalogue is exposed; override per subclass. */
    protected ArrayClass $restrictedTools {
        get => new ArrayClass();
    }

    /**
     * Invokes one registry tool by name with the request's `tool` and `arguments`
     * parameters and exposes its JSON result as the response data.
     *
     * The tools a subclass exposes are bounded by {@see $restrictedTools}; the tools
     * themselves enforce their own RBAC on top, so this surface stays behind the
     * same two layers every other endpoint relies on. The base exposes the whole
     * catalogue; narrow it by overriding {@see $restrictedTools} in a subclass.
     *
     * @throws Throwable
     */
    #[Action(transformers: [JSONTransformer::class])]
    public function callTool(): void
    {
        $parameters = $this->request->parameters;
        $name = (string)($parameters["tool"] ?? throw new BadRequestException("`tool` is required"));
        $this->restrictedTools->containsElement($name) ?: throw new BadRequestException("`$name` is not available here.");
        /** @var Dictionary<mixed> $arguments */
        $arguments = $parameters["arguments"] ?? new Dictionary();
        $result = $this->registry->call($name, $arguments);
        $this->data = Dictionary::dictionaryWithArray(json_decode($result->text, true) ?? []);
    }
}
