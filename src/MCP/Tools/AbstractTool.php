<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Exception;
use JsonException;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\Application;
use Sabatier\Service\Authorizable;
use Sabatier\Service\AuthorizationContext;
use Sabatier\Service\AuthorizationService;
use Sabatier\Service\AuthorizationType;
use Sabatier\Service\DefaultAccessPolicy;
use Sabatier\Service\FieldLevelSecurityPolicy;
use Sabatier\Service\FieldSecurityPolicy;
use Sabatier\Service\ForbiddenException;
use Sabatier\Service\MCP\Response\ContentItem;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\ToolResolver;
use function Sabatier\Foundation\localized_string;
use const Sabatier\CoreData\ManagedObjectEntityNameKey;

/**
 * Base class for all MCP tools, both built-in and custom.
 *
 * Extend this class and place the subclass in the application's `MCPTools`
 * directory to register a custom tool with the MCP server.
 *
 * @psalm-consistent-constructor
 * @phpstan-consistent-constructor
 */
abstract class AbstractTool
{
    /** @var string Stable name advertised to clients and used to dispatch calls. */
    abstract public string $name {
        get;
    }
    /** @var string Human-readable display name; defaults to vocabulary `title` or falls back to `name`. */
    public string $title {
        get => $this->vocabulary->localize($this->name, "title") ?? $this->name;
    }
    /** @var string Description read by the LLM client; defaults to vocabulary `description` or falls back to `name`. */
    public string $description {
        get => $this->vocabulary->localize($this->name, "description") ?? $this->name;
    }
    /** @var array<array-key, mixed> JSON Schema describing the arguments accepted by the tool. */
    abstract public array $inputSchema {
        get;
    }
    /** @var bool Whether every call only reads state; also published as MCP readOnlyHint. Defaults to `false`. A tool that reads and writes is two tools, each declaring what it is. */
    protected(set) bool $isReadOnly = false;
    /** @var bool Whether a repeated call with identical arguments may be served from the run's cache instead of executed again. Reading state is necessary but not sufficient: a tool that reads something which moves on its own — the clock, a queue depth — answers differently to the same arguments, and caching it would freeze the first answer for the rest of the run. Such a tool stays read-only and overrides this to `false`. */
    public bool $isCacheable {
        get => $this->isReadOnly;
    }
    /** @var bool MCP hint: a writing tool may overwrite or remove state. Defaults to `true`; meaningful only when `isReadOnly` is false. */
    protected(set) bool $isDestructive = true;
    /** @var bool MCP hint: repeating identical arguments has no additional effects. Defaults to `false`; this does not authorize retries or imply cacheability. */
    protected(set) bool $isIdempotent = false;
    /** @var bool MCP hint: the tool may interact with an open world of external entities. Override to `false` for a closed application domain. */
    protected(set) bool $isOpenWorld = true;
    /** @var string|null The `ui://` uri of the resource a host renders this tool's answer in, or null for a tool answering as text alone. */
    protected(set) ?string $uiResourceUri = null;
    /** @var Dictionary<mixed> The validated arguments of the running call, assigned by `call()` before anything reads them; the registry runs each call on a clone of the tool, so a hook derived from them may memoize. */
    protected(set) Dictionary $arguments;
    /** @var string|null The resource the running call is authorized against, or null when it needs no authorization; the `entityName` argument by default. */
    public ?string $authorizationResource {
        get => is_string($entityName = $this->arguments[ManagedObjectEntityNameKey]) ? $entityName : null;
    }
    /** @var AuthorizationType The action the running call is authorized for; `read` for a read-only tool and `update` otherwise by default. */
    public AuthorizationType $authorizationAction {
        get => $this->isReadOnly ? AuthorizationType::read : AuthorizationType::update;
    }
    /** @var array<array-key, mixed>|null The structured payload the running call contributed, or null when it contributed none; the registry runs each call on a clone of the tool, so it never carries from one call into the next. */
    private(set) ?array $structuredContent = null;
    /** @var ToolVocabulary The vocabulary of the bundle that owns this concrete tool class. */
    private ToolVocabulary $vocabulary {
        get => $this->vocabulary ??= ToolVocabulary::forBundle(Bundle::bundleForClass(static::class));
    }
    /** @var bool Whether security enforcement is enabled for this request. */
    protected bool $isSecurityEnabled {
        get => Application::shared()->accessPolicy instanceof DefaultAccessPolicy;
    }
    /** @var AuthorizationContext Authorization context derived from the current authentication. */
    protected AuthorizationContext $authorizationContext {
        get => $this->authorizationContext ??= new AuthorizationContext(Application::shared()->authenticationManager->authentication->authenticatedUser, Application::shared()->authenticationManager->authentication->authorizationScopes, $this->isSecurityEnabled);
    }
    /** @var AuthorizationService The service that resolves the user's authorizations. */
    protected AuthorizationService $authorizationService {
        get => Application::shared()->authorizationService;
    }
    /** @var FieldSecurityPolicy Security policy used for field-level read/write enforcement. */
    protected FieldSecurityPolicy $fieldSecurityPolicy {
        get => $this->fieldSecurityPolicy ??= new FieldLevelSecurityPolicy($this->authorizationContext);
    }
    /** @var Authorizable|null The authenticated user for policy evaluation. */
    protected ?Authorizable $user {
        get => $this->fieldSecurityPolicy->user;
    }
    /** @var Dictionary<AbstractTool> Every tool the application resolves, keyed by name. Resolving the catalogue is the responder's work, not a tool's; this is here for a tool that was written against it and will be removed. @internal */
    protected Dictionary $tools {
        get => $this->tools ??= new ToolResolver($this->context, $this->descriptor)->resolve()->reduce(new Dictionary(),
            /**
             * @param Dictionary<AbstractTool> $tools
             * @return Dictionary<AbstractTool>
             */
            function (Dictionary $tools, AbstractTool $tool): Dictionary {
                $tools[$tool->name] = $tool;
                return $tools;
            });
    }

    /**
     * @param ManagedObjectContext $context The context used to execute the tool's data operations.
     * @param ModelDescriptor $descriptor The model schema exposed to the tool.
     */
    public function __construct(protected readonly ManagedObjectContext $context, protected readonly ModelDescriptor $descriptor)
    {
    }

    /**
     * Invokes the tool: keeps the arguments as {@see self::$arguments}, authorizes the call, then runs its core.
     *
     * @param Dictionary<mixed> $arguments The validated arguments supplied by the caller.
     * @return ArrayClass<ContentItem> The content returned to the caller.
     * @throws Exception
     */
    final public function call(Dictionary $arguments): ArrayClass
    {
        $this->arguments = $arguments;
        $this->authorize();
        return $this->executeCore();
    }

    /**
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    abstract protected function executeCore(): ArrayClass;

    /**
     * Enforces per-entity RBAC for the resource this tool call targets — the check
     * `AuthorizationEvaluator` performs by URL for regular endpoints, which never sees the
     * entity an MCP tool operates on because the request URL is always `/mcp`. Both ask
     * the application's access policy through `allowsAccess()`.
     * @throws Exception
     */
    protected function enforceEntityAuthorization(string $resource, AuthorizationType $action): void
    {
        if (!$this->isSecurityEnabled) {
            return;
        }
        $user = $this->user ?? throw new ForbiddenException(localized_string("You must be authenticated to perform this action."));
        Application::shared()->accessPolicy->allowsAccess($resource, $action, $user, $this->fieldSecurityPolicy->scopes, $this->authorizationService, $this->context) ?: throw new ForbiddenException(sprintf(localized_string("You don't have permission to %s \"%s\"."), $action->name, $resource));
    }

    /**
     * @throws Exception
     */
    private function authorize(): void
    {
        if ($resource = $this->authorizationResource) {
            $this->enforceEntityAuthorization($resource, $this->authorizationAction);
        }
    }

    /** @return ArrayClass<ContentItem> */
    protected function textResult(string $text): ArrayClass
    {
        return new ArrayClass([new ContentItem("text", $text)]);
    }

    /**
     * Serializes the data as the text the model reads, and keeps it as the call's structured payload.
     *
     * The payload is taken from the serialization rather than from the argument, so a `Dictionary`, an `ArrayClass`
     * or a managed object answers with the same shape the client is sent. A payload that serializes to a JSON array
     * is left out: `structuredContent` is defined as an object.
     *
     * @return ArrayClass<ContentItem>
     * @throws JsonException
     */
    protected function jsonResult(mixed $data): ArrayClass
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (is_array($decoded) && !array_is_list($decoded)) {
            $this->structuredContent = $decoded;
        }
        return $this->textResult($json);
    }

    /**
     * Contributes a structured payload alongside content the tool has already built, for a tool whose text is prose
     * rather than the serialization of that payload.
     *
     * A tool that answers with {@see self::jsonResult()} needs none of this: the payload it passes there is captured
     * as it is serialized.
     *
     * @param ArrayClass<ContentItem> $content The content the caller reads, returned unchanged.
     * @param array<array-key, mixed> $data The structured payload a UI reads.
     * @return ArrayClass<ContentItem>
     */
    protected function structuredResult(ArrayClass $content, array $data): ArrayClass
    {
        $this->structuredContent = $data;
        return $content;
    }
}
