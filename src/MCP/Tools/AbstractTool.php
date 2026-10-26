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
    /** @var bool Whether every call only reads state; also published as MCP readOnlyHint. Defaults to `false`; mixed tools classify individual calls through isReadOnlyCall() without advertising the whole tool as read-only. */
    public bool $isReadOnly {
        get => false;
    }
    /** @var bool Whether a repeated call with identical arguments may be served from the run's cache instead of executed again. Reading state is necessary but not sufficient: a tool that reads something which moves on its own — the clock, a queue depth — answers differently to the same arguments, and caching it would freeze the first answer for the rest of the run. Such a tool stays read-only and overrides this to `false`. */
    public bool $isCacheable {
        get => $this->isReadOnly;
    }
    /** @var bool MCP hint: a writing tool may overwrite or remove state. Defaults to `true`; meaningful only when `isReadOnly` is false. */
    public bool $isDestructive {
        get => true;
    }
    /** @var bool MCP hint: repeating identical arguments has no additional effects. Defaults to `false`; this does not authorize retries or imply cacheability. */
    public bool $isIdempotent {
        get => false;
    }
    /** @var bool MCP hint: the tool may interact with an open world of external entities. Override to `false` for a closed application domain. */
    public bool $isOpenWorld {
        get => true;
    }
    /** @var string|null The `ui://` uri of the resource a host renders this tool's answer in, or null for a tool answering as text alone. */
    public ?string $uiResourceUri {
        get => null;
    }
    /** @var array<array-key, mixed>|null The structured payload the running call contributed, or null when it contributed none. The registry reuses one instance of each tool across calls, so `call()` clears this before the core runs and the value is read once after it returns; it never carries from one call into the next. */
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
     * Whether this concrete invocation only reads state.
     *
     * @param Dictionary<mixed> $arguments The arguments selecting the concrete operation.
     */
    public function isReadOnlyCall(/** @noinspection PhpUnusedParameterInspection */ Dictionary $arguments): bool
    {
        return $this->isReadOnly;
    }

    /**
     * The resource this invocation is authorized against, or null when it needs no authorization; the `entity` argument by default.
     *
     * @param Dictionary<mixed> $arguments The arguments selecting the concrete operation.
     */
    public function authorizationResource(Dictionary $arguments): ?string
    {
        return is_string($entity = $arguments["entity"]) ? $entity : null;
    }

    /**
     * The action this invocation is authorized for; `read` for a read-only call and `update` otherwise by default.
     *
     * @param Dictionary<mixed> $arguments The arguments selecting the concrete operation.
     */
    public function authorizationAction(Dictionary $arguments): AuthorizationType
    {
        return $this->isReadOnlyCall($arguments) ? AuthorizationType::read : AuthorizationType::update;
    }

    /**
     * Invokes the tool: authorizes the call, then runs its core.
     *
     * The structured payload of any earlier call is cleared first, so what {@see self::$structuredContent} holds
     * afterwards was contributed by this call or by nothing at all.
     *
     * @param Dictionary<mixed> $arguments The validated arguments supplied by the caller.
     * @return ArrayClass<ContentItem> The content returned to the caller.
     * @throws Exception
     */
    final public function call(Dictionary $arguments): ArrayClass
    {
        $this->authorize($arguments);
        $this->structuredContent = null;
        return $this->executeCore($arguments);
    }

    /**
     * @param Dictionary<mixed> $arguments
     * @return ArrayClass<ContentItem>
     * @throws Exception
     */
    abstract protected function executeCore(Dictionary $arguments): ArrayClass;

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
     * @param Dictionary<mixed> $arguments
     * @throws Exception
     */
    private function authorize(Dictionary $arguments): void
    {
        if ($resource = $this->authorizationResource($arguments)) {
            $this->enforceEntityAuthorization($resource, $this->authorizationAction($arguments));
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
