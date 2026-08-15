<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\Set;
use Throwable;
use function Sabatier\Foundation\human_readable_value;

/**
 * An abstract class for responding to and handling url requests.
 * @psalm-consistent-constructor
 * @phpstan-consistent-constructor
 */
abstract class Responder extends ObjectClass
{
    /** @var Request The request associated with this responder. */
    public Request $request {
        get => self::$staticAssociatedValues[self::class][__PROPERTY__] ??= new Request();
    }
    protected Session $session {
        get => self::$staticAssociatedValues[self::class][__PROPERTY__] ??= new Session();
    }
    /** @var Dictionary<string> The environment variables associated with this responder. */
    protected Dictionary $environment {
        get => ProcessInfo::processInfo()->environment;
    }
    /** @var CORSPolicy The CORS policy applied to the response produced by this responder. */
    protected CORSPolicy $corsPolicy {
        get => $this->corsPolicy = new CORSPolicy(Application::shared()->corsPolicy->allowedOrigins, Application::shared()->corsPolicy->allowedMethods->intersection(new Set($this->allowedMethods)), Application::shared()->corsPolicy->allowedHeaders->intersection(new Set($this->allowedHeaders)), Application::shared()->corsPolicy->allowCredentials, Application::shared()->corsPolicy->exposedHeaders);
    }
    protected AccessPolicy $accessPolicy {
        get => Application::shared()->accessPolicy;
    }
    /** @var SecurityHeadersPolicy The security policy applied to the response produced by this responder. */
    protected SecurityHeadersPolicy $securityHeadersPolicy {
        get => Application::shared()->securityHeadersPolicy;
    }
    /** @var HTTPCachePolicy The HTTP cache policy controlling ETag generation and Cache-Control defaults for this responder. */
    protected HTTPCachePolicy $cachePolicy {
        get => Application::shared()->cachePolicy;
    }
    /** @var RateLimitPolicy The rate limiting policy in effect for this responder. */
    protected RateLimitPolicy $rateLimitPolicy {
        get => Application::shared()->rateLimitPolicy;
    }
    /** @var IdempotencyPolicy The idempotency policy controlling replay behavior for POST and PATCH requests. */
    protected IdempotencyPolicy $idempotencyPolicy {
        get => Application::shared()->idempotencyPolicy;
    }
    /** @var ResponseTransformerContext The transformer context assembling the request and all policies for this responder's response pipeline. Override to customize which context is propagated to internal transformers. */
    protected ResponseTransformerContext $transformerContext {
        get => $this->transformerContext ??= new ResponseTransformerContext($this->request, $this->cachePolicy, $this->corsPolicy, $this->securityHeadersPolicy, Application::shared()->rateLimitInfo);
    }
    /** @var ManagedObjectContext The managed object context associated with this responder. */
    public ManagedObjectContext $managedObjectContext {
        get => Application::shared()->persistentContainer->viewContext;
    }
    protected AuthenticationService $authenticationService {
        get => Application::shared()->authenticationService;
    }
    protected AuthorizationService $authorizationService {
        get => Application::shared()->authorizationService;
    }
    /** @var AuthorizationContext Authorization context derived from the current authentication. */
    protected AuthorizationContext $authorizationContext {
        get => $this->authorizationContext ??= new AuthorizationContext(Application::shared()->authenticationManager->authentication->authenticatedUser, Application::shared()->authenticationManager->authentication->authorizationScopes, $this->isSecurityEnabled);
    }
    /** @var string|null The username from the current request credential, if any. Resolved from the credential header only — it does not load the user entity, so it is safe and cheap to read on the error path. */
    protected ?string $currentUsername {
        get {
            try {
                return Application::shared()->authenticationManager->authentication->credential?->user;
            } catch (Throwable) {
                return null;
            }
        }
    }
    /** @var FieldSecurityPolicy Security policy used for field-level read/write enforcement. */
    protected FieldSecurityPolicy $fieldSecurityPolicy {
        get => $this->fieldSecurityPolicy ??= new FieldLevelSecurityPolicy($this->authorizationContext);
    }
    /** @var ArrayClass<string> The allowed methods associated with this responder. */
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::head, HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::patch, HTTPRequestMethod::delete]);
    }
    /** @var ArrayClass<string> Defines the HTTP headers this responder is capable of understanding. It does not grant permission by itself; the effective allowed headers are the intersection between the responder’s declared headers and the application’s global CORS policy. */
    protected ArrayClass $allowedHeaders {
        get => new ArrayClass(["Content-Type", "Authorization", "Serialization", "If-None-Match", "Cache-Control", "Idempotency-Key"]);
    }
    /** @var Responder|null The next responder. */
    public ?Responder $nextResponder = null;
    private ResponderResolution $resolution {
        /**
         * @throws Exception
         */
        get => $this->resolution ??= new ResponderResolution(static::class, $this->request->url->path);
    }
    /** @var bool Returns a Boolean value indicating whether this object is the first responder. */
    public bool $isFirstResponder {
        get => $this->resolution->matches;
    }
    /** @var string|null The selector associated with this responder. */
    protected ?string $selector {
        get => $this->resolution->selector;
    }
    /** @var Set<class-string<ResponseTransformer>> The ordered set of response transformers applied to this responder. Sourced from the #[Endpoint] and #[Action] attributes. Override to declare additional transformers; the infrastructure layer (ConditionalGetTransformer, RateLimitHeaderTransformer, SecurityHeadersTransformer, CORSResponseTransformer) is always applied unconditionally after this set and must not be included here. */
    protected Set $transformers {
        get => $this->transformers ??= $this->resolution->transformers;
    }
    /** @var Set<class-string<ResponseTransformer>> The fixed infrastructure transformer layer applied after the user pipeline on every response. Always runs regardless of subclass overrides to $transformers. */
    protected Set $infrastructureTransformers {
        get => $this->infrastructureTransformers ??= new Set([CacheHeaderTransformer::class, ConditionalGetTransformer::class, RateLimitHeaderTransformer::class, SecurityHeadersTransformer::class, CORSResponseTransformer::class]);
    }
    /** @var mixed The data produced or returned by the responder's action method. This value is used as the body of the response or as input to response transformers. */
    protected mixed $data = null;
    /** @var int The HTTP status code to be returned in the response. */
    #[ExpectedValues(valuesFromClass: HTTPStatusCode::class)]
    protected int $statusCode = HTTPStatusCode::ok;
    /** @var bool Checks if the protected content is available by determining if the request is authorized. */
    public bool $isProtectedContentAvailable = false;
    protected bool $isSecurityEnabled {
        get => $this->accessPolicy instanceof DefaultAccessPolicy;
    }
    /** @var bool Determines if the infrastructure should use session-based persistence as a fallback mechanism when high-security authentication providers are not configured. */
    protected bool $isSessionEnabled {
        get => $this->isSecurityEnabled && !$this->environment->offsetExists(JWTPrivateKey);
    }
    protected ?string $idempotencyKey {
        get => $this->resolveIdempotencyKey($this->request);
    }
    protected ?IdempotentResponse $idempotentResponse {
        get {
            if (!($key = $this->idempotencyKey)) {
                return null;
            }
            return Application::shared()->idempotencyStore->get($key);
        }
    }
    /** @var Response The response associated with this responder. */
    public Response $response {
        get {
            try {
                $request = $this->request;
                $this->allowedMethods->containsElement($request->httpMethod) ?: throw new MethodNotAllowedException();
                if ($this->isSessionEnabled) {
                    $this->session->start();
                }
                $idempotencyKey = $this->idempotencyKey;
                if ($idempotencyKey !== null) {
                    if ($stored = $this->idempotentResponse) {
                        if ($stored->isProcessing) {
                            throw new ConflictException();
                        }
                        return new ResponsePipeline($this->infrastructureTransformers, $this->transformerContext)->process(new Response($request->url, $stored->statusCode, $stored->headers, $stored->body));
                    }
                    $this->markInFlight($idempotencyKey);
                }
                if (match ($request->httpMethod) {
                        HTTPRequestMethod::post,
                        HTTPRequestMethod::patch,
                        HTTPRequestMethod::delete => true,
                        default => false,
                    } && ($selector = $this->selector)) {
                    $this->perform($selector);
                }
                // Read in this order deliberately: producing the body is what runs the action, and
                // a responder may build part of its transformer context from what the action left
                // behind — MCPResponder carries the session identifier its handshake issued.
                // Passing both inline would evaluate the context first, since PHP reads arguments
                // left to right, handing the pipeline a context assembled before the work it
                // describes had happened.
                $data = $this->data;
                $transformerContext = $this->transformerContext;
                $userResponse = new ResponsePipeline($this->transformers, $transformerContext)->process(new Response($request->url, $this->statusCode, body: $data));
                if ($idempotencyKey !== null) {
                    $this->storeIdempotentResponse($idempotencyKey, $userResponse);
                }
                return new ResponsePipeline($this->infrastructureTransformers, $this->transformerContext)->process($userResponse);
            } finally {
                if ($this->isSessionEnabled) {
                    $this->session->commit();
                }
            }
        }
    }

    protected function resolveIdempotencyKey(Request $request): ?string
    {
        $policy = $this->idempotencyPolicy;
        if (!$policy->enabled || !match ($request->httpMethod) {
                HTTPRequestMethod::post, HTTPRequestMethod::patch => true,
                default => false
            }) {
            return null;
        }
        $raw = $request->valueForHttpHeaderField($policy->headerName);
        if ($raw === null) {
            return null;
        }
        strlen($raw) <= IdempotencyKeyMaxLength ?: throw new BadRequestException();
        $userIdentity = $this->currentUsername ?? "anonymous";
        return "$raw:$request->httpMethod:{$request->url->path}:$userIdentity";
    }

    protected function markInFlight(string $key): void
    {
        Application::shared()->idempotencyStore->store($key, new IdempotentResponse(HTTPStatusCode::accepted, new Dictionary(), null, true), IdempotencyInFlightTTL);
    }

    protected function storeIdempotentResponse(string $key, Response $response): void
    {
        /** @var Dictionary<string> $headers */
        $headers = $response->allHeaderFields->mapValues(fn(mixed $value): string => human_readable_value($value));
        Application::shared()->idempotencyStore->store($key, new IdempotentResponse($response->statusCode, $headers, $response->body), $this->idempotencyPolicy->ttl);
    }
}
