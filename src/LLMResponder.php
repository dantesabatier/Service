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
use Sabatier\Service\MCP\Tools\AbstractTool;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use stdClass;
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
    /** @var ToolRegistry The catalogue this responder exposes, already narrowed by {@see $restrictedTools} so every surface reading it — the raw tool call, an agent loop, a `tools/list` — sees the same tools. Filtering at each consumer instead leaves a restricted tool listed and callable through whichever one was overlooked. */
    protected ToolRegistry $registry {
        get {
            if (isset($this->registry)) {
                return $this->registry;
            }
            $restricted = $this->restrictedTools;
            $resolved = new ToolResolver($this->managedObjectContext, $this->descriptor)->resolve();
            return $this->registry = new ToolRegistry($restricted->isEmpty ? $resolved : $resolved->filter(fn(AbstractTool $tool): bool => !$restricted->containsElement($tool->name)));
        }
    }
    /** @var ArrayClass<string> Tools this responder withholds from the registry it builds, by name. Empty by default — the whole catalogue is exposed; override per subclass. */
    protected ArrayClass $restrictedTools {
        get => new ArrayClass();
    }

    /**
     * Invokes one registry tool by name with the request's `tool` and `arguments`
     * parameters and exposes its JSON result as the response data.
     *
     * The tools a subclass exposes are bounded by {@see $restrictedTools}, which narrows
     * the registry itself rather than this surface, so a restricted tool is absent from
     * every reader of the catalogue and not merely refused here. The tools themselves
     * enforce their own RBAC on top, so this surface stays behind the same two layers
     * every other endpoint relies on.
     *
     * @throws Throwable
     */
    #[Action(transformers: [JSONTransformer::class])]
    public function callTool(): void
    {
        $parameters = $this->request->parameters;
        $name = (string)($parameters["tool"] ?? throw new BadRequestException("`tool` is required"));
        // An `argument` that is not an object is the caller's mistake, not the model's: without this guard it would reach the registry as an ArrayClass and die on a TypeError that never says what was sent wrong.
        $arguments = $parameters["arguments"] ?? new Dictionary();
        $arguments instanceof Dictionary ?: throw new BadRequestException("`arguments` must be an object.");
        $result = $this->registry->call($name, $arguments);
        $this->data = Dictionary::dictionaryWithArray(json_decode($result->text) ?? new stdClass());
    }
}
