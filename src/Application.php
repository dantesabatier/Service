<?php

declare(strict_types=1);

namespace Sabatier\Service;

use ErrorException;
use Exception;
use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\PersistentContainer;
use Sabatier\CoreData\PersistentStoreDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Notification;
use Sabatier\Foundation\NotificationCenter;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\UserDefaults;
use Throwable;
use function Sabatier\Foundation\fatal_error;
use const Sabatier\CoreData\DeletedObjectsKey;
use const Sabatier\CoreData\InsertedObjectsKey;
use const Sabatier\CoreData\ManagedObjectContextDidSave;
use const Sabatier\CoreData\PersistentHistoryTrackingKey;
use const Sabatier\CoreData\PersistentStoreRemoteChange;
use const Sabatier\CoreData\PersistentStoreRemoteChangeNotificationPostOptionKey;
use const Sabatier\CoreData\UpdatedObjectsKey;
use const Sabatier\Foundation\kCFBundleNameKey;

/**
 * The central coordinator for request handling, persistence, session
 * management, authentication/authorization, and lifecycle events.
 *
 * `Application` is the root of the responder chain and exposes shared
 * services (persistent container, auth services, policies, session).
 * It is a singleton accessed via `Application::shared()`.
 *
 * ## Responsibilities
 * - Initializes and configures the Core Data stack.
 * - Manages application delegate lifecycle events.
 * - Handles preflight requests and shutdown/crash reporting.
 * - Resolves the first responder for each request.
 * - Applies access control and sets the transaction author.
 * - Executes the main run loop and guarantees a structured error response.
 *
 * ## Customization
 * You can override or replace:
 * - `authorizationCache` to use custom cache backends.
 * - `authorizationService` to replace authorization logic.
 * - `accessPolicy` to define custom access-control behavior.
 * - `corsPolicy` and `staticResourcePolicy` for platform-level behavior.
 *
 * ## Run Loop (High-Level)
 * 1. Bootstrap and delegate initialization
 * 2. Preflight handling (when needed)
 * 3. Application initialization
 * 4. Access enforcement and transaction author assignment
 * 5. Response processing and delivery
 *
 * Any uncaught exception is converted into a safe, structured error response.
 */
class Application extends Responder
{
    private static ?Application $shared = null;
    /** @var ApplicationDelegate|null The delegate of the app object. */
    private(set) ?ApplicationDelegate $delegate {
        get => $this->delegate ??= $this->initializeDelegate();
    }
    /** @var PersistentContainer The persistent container for Core Data operations. */
    private(set) PersistentContainer $persistentContainer {
        get => $this->persistentContainer ??= $this->createPersistentContainer();
    }
    /** @var Responder The first responder in the responder chain. */
    private(set) Responder $firstResponder {
        get => $this->firstResponder ??= new FirstResponderResolver($this, $this->authenticationManager)->firstResponder;
    }
    /**
     * @var AuthenticationService The authentication service for managing user authentication.
     * @disregard P1070 Visibility restriction intentional for readonly semantic
     */
    #[Override]
    private(set) AuthenticationService $authenticationService {
        get => $this->authenticationService ??= new AuthenticationService($this->persistentContainer->managedObjectModel);
    }
    private AuthorizationResolver $authorizationResolver {
        get => $this->authorizationResolver ??= new AuthorizationResolver($this->persistentContainer->managedObjectModel);
    }
    private AuthorizableTokenInvalidator $authorizableTokenInvalidator {
        get => $this->authorizableTokenInvalidator ??= new AuthorizableTokenInvalidator($this->persistentContainer->managedObjectModel);
    }
    /** @var AuthorizationCache The in-request authorization cache for storing authorization data. */
    public AuthorizationCache $authorizationCache {
        get => $this->authorizationCache ??= new InMemoryAuthorizationCache();
    }
    /** @var AuthorizationCache|null An optional cross-request authorization cache (e.g., Redis, APCu, or Memcached). Null disables persistent caching. Set this in the application delegate to reduce database round-trips for repeat requests by the same user. */
    public ?AuthorizationCache $authorizationPersistentCache = null;
    /** @var AuthorizationService The authorization service for managing user authorization. */
    #[Override]
    public AuthorizationService $authorizationService {
        get => $this->authorizationService ??= new AuthorizationService($this->authorizationResolver, $this->authorizationCache, $this->authorizationPersistentCache);
    }
    /** @var AuthenticationManager The authentication manager for handling authentication processes. */
    private(set) AuthenticationManager $authenticationManager {
        get => $this->authenticationManager ??= new AuthenticationManager();
    }
    /** @var CORSPolicy The CORS policy applied to all incoming requests. This policy defines which origins, HTTP methods, and headers are permitted for cross-origin requests and whether credentials are allowed. */
    #[Override]
    public CORSPolicy $corsPolicy {
        get => $this->corsPolicy ??= CORSPolicy::policy();
    }
    #[Override]
    public SecurityHeadersPolicy $securityHeadersPolicy {
        get => $this->securityHeadersPolicy ??= SecurityHeadersPolicy::policy();
    }
    /** @var AccessPolicy The access policy for enforcing access control. */
    #[Override]
    public AccessPolicy $accessPolicy {
        get => $this->accessPolicy ??= new DefaultAccessPolicy();
    }
    /** @var StaticResourcePolicy Policy used to determine how the application handles static resources. */
    public StaticResourcePolicy $staticResourcePolicy {
        get => $this->staticResourcePolicy ??= new DefaultStaticResourcePolicy();
    }
    /** @var HTTPCachePolicy The HTTP cache policy that controls caching behavior (ETags, Cache-Control directives) for all responses. Override this property in the application delegate to customize the default policy. */
    #[Override]
    public HTTPCachePolicy $cachePolicy {
        get => $this->cachePolicy ??= HTTPCachePolicy::policy();
    }
    /** @var RateLimitPolicy The rate limiting policy controlling request quotas per client. Override in the application delegate to customize limits or disable rate limiting. */
    #[Override]
    public RateLimitPolicy $rateLimitPolicy {
        get => $this->rateLimitPolicy ??= RateLimitPolicy::policy();
    }
    /** @var RateLimitStore The storage backend used to track request counters. Defaults to APCuRateLimitStore (shared memory across workers). Override with a Redis-backed store for multi-server deployments. */
    public RateLimitStore $rateLimitStore {
        get => $this->rateLimitStore ??= new APCuRateLimitStore();
    }
    /** @var IdempotencyPolicy The idempotency policy controlling replay behavior for POST and PATCH requests. */
    #[Override]
    public IdempotencyPolicy $idempotencyPolicy {
        get => $this->idempotencyPolicy ??= IdempotencyPolicy::policy();
    }
    /** @var IdempotencyStore The storage backend used to persist and retrieve idempotent response snapshots. Defaults to APCuIdempotencyStore. Override with a Redis or Memcached store for multi-server deployments. */
    public IdempotencyStore $idempotencyStore {
        get => $this->idempotencyStore ??= new APCuIdempotencyStore();
    }
    /** @var Set<string> Trusted proxy IP addresses whose `X-Forwarded-For` header may be believed when resolving the client address. Empty (the default) means the client address is always `REMOTE_ADDR`. Declare your load balancer / CDN egress IPs here in the delegate. */
    public Set $trustedProxies {
        get => $this->trustedProxies ??= new Set();
    }
    /** @var GeoIPResolver|null Optional resolver mapping the client IP to a country code, exposed as `$REQUEST.country` in attribute-based access conditions. Null (the default) leaves `$REQUEST.country` unresolved, so country-based rules fail closed. Set in the delegate to enable geolocation. */
    public ?GeoIPResolver $geoIPResolver {
        get => $this->geoIPResolver ?? null;
    }
    /** @var RateLimitInfo|null The rate limit state produced for the current request. Populated by enforceRateLimitIfNeeded() and consumed by RateLimitHeaderTransformer via the transformer context. */
    private(set) ?RateLimitInfo $rateLimitInfo = null;
    private bool $isTerminated = false;
    private bool $isBootstrapped = false;

    private function bootstrapIfNeeded(): void
    {
        if ($this->isBootstrapped) {
            return;
        }
        $delegate = $this->delegate;
        if ($delegate) {
            $this->initializeDelegateClass($delegate::class);
        }
        $this->isBootstrapped = true;
    }

    private function initializeDelegate(): ?ApplicationDelegate
    {
        /** @var class-string<ApplicationDelegate> $principalClass */
        $principalClass = (string)Bundle::main()->principalClass;
        /** @var array<string, class-string> $implementations */
        $implementations = class_implements($principalClass);
        if (!isset($implementations[ApplicationDelegate::class])) {
            return null;
        }
        return new $principalClass();
    }

    /**
     * @param class-string<ApplicationDelegate> $delegateClass
     */
    private function initializeDelegateClass(string $delegateClass): void
    {
        if (is_subclass_of($delegateClass, ObjectClass::class)) {
            /** @var class-string<ObjectClass> $objectClass */
            $objectClass = $delegateClass;
            $objectClass::initialize();
        }
    }

    private function createPersistentContainer(): PersistentContainer
    {
        $persistentContainer = new PersistentContainer(Bundle::main()->object(kCFBundleNameKey));
        $this->configurePersistentStoreDescriptions($persistentContainer);
        $this->initializePersistentStores($persistentContainer);
        $this->addPersistentStoreObservers($persistentContainer);
        return $persistentContainer;
    }

    private function configurePersistentStoreDescriptions(PersistentContainer $persistentContainer): void
    {
        if ($description = $persistentContainer->persistentStoreDescriptions->first) {
            $description->setOptionForKey(UserDefaults::standard()->bool(PersistentHistoryTrackingKey), PersistentHistoryTrackingKey);
            $description->setOptionForKey(UserDefaults::standard()->bool(PersistentStoreRemoteChangeNotificationPostOptionKey), PersistentStoreRemoteChangeNotificationPostOptionKey);
        }
    }

    private function initializePersistentStores(PersistentContainer $persistentContainer): void
    {
        $persistentContainer->loadPersistentStores(function (PersistentStoreDescription $description, ?Error $error): void {
            $error === null ?: throw new InternalInconsistencyException(error: $error);
        });
    }

    private function addPersistentStoreObservers(PersistentContainer $persistentContainer): void
    {
        NotificationCenter::default()->addObserverForName(PersistentStoreRemoteChange, $persistentContainer->persistentStoreCoordinator, function (Notification $notification): void {
            $this->handlePersistentStoreRemoteChange($notification);
        });
        NotificationCenter::default()->addObserverForName(ManagedObjectContextDidSave, null, function (Notification $notification): void {
            $this->handleAuthorizationEntitiesDidSave($notification);
        });
    }

    /**
     * @throws Exception
     */
    private function handleAuthorizationEntitiesDidSave(Notification $notification): void
    {
        /** @var Dictionary<Set<ManagedObject>> $userInfo */
        $userInfo = $notification->userInfo ?? fatal_error("Missing userInfo in notification: $notification");
        if (new ArrayClass([InsertedObjectsKey, UpdatedObjectsKey, DeletedObjectsKey])->contains(fn(string $key): bool => (bool)$userInfo[$key]?->contains(fn(ManagedObject $object): bool => $object instanceof Authorization || $object instanceof AuthorizableRole))) {
            $this->authorizationService->invalidateAll();
            $this->invalidateAuthorizableTokens();
        }
    }

    /**
     * @throws Exception
     */
    private function invalidateAuthorizableTokens(): void
    {
        $this->authorizableTokenInvalidator->invalidate($this->persistentContainer->viewContext);
    }

    /**
     * @throws Exception
     */
    private function handlePersistentStoreRemoteChange(Notification $notification): void
    {
    }

    private function handlePreflightIfNeeded(): void
    {
        $responder = new PreflightResponder();
        $responder->respondToPreflightIfNeeded();
    }

    private function handleShutdown(): void
    {
        /** @var array{type: int, message: string, file: string, line: int}|null $error */
        $error = error_get_last();
        if ($error) {
            ["message" => $message, "type" => $type, "file" => $file, "line" => $line] = $error;
            if (match ($type) {
                    E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR,
                    E_USER_ERROR, E_RECOVERABLE_ERROR => true,
                    default => false
                } && !$this->isTerminated) {
                $exception = new ErrorException(message: $message, code: $type, filename: $file, line: $line);
                try {
                    $this->delegate?->applicationDidCrash($this, $exception);
                } catch (Throwable $throwable) {
                    error_log("$this->debugDescription applicationDidCrash threw: $throwable");
                }
                if (!headers_sent()) {
                    $this->handle($exception);
                }
            }
            return;
        }
        $this->delegate?->applicationWillTerminate($this);
    }

    private function initializeApplication(): void
    {
        $delegate = $this->delegate;
        $delegate?->applicationWillFinishLaunching($this);
        $persistentContainer = $this->persistentContainer;
        $viewContext = $persistentContainer->viewContext;
        $viewContext->name = $persistentContainer->name;
        ProcessInfo::processInfo()->processName = $persistentContainer->name;
        register_shutdown_function($this->handleShutdown(...));
    }

    private function enforceRateLimitIfNeeded(): void
    {
        $policy = $this->rateLimitPolicy;
        if (!$policy->enabled) {
            return;
        }
        $username = $this->authenticationManager->authentication->credential?->user;
        [$key, $limit] = $username !== null ? ["rate_limit:user:$username", $policy->maxRequestsUser] : ["rate_limit:ip:" . ($_SERVER["REMOTE_ADDR"] ?? "unknown"), $policy->maxRequestsIP];
        $count = $this->rateLimitStore->increment($key, $policy->windowSeconds);
        $ttl = $this->rateLimitStore->ttl($key);
        $reset = time() + $ttl;
        $remaining = max(0, $limit - $count);
        $this->rateLimitInfo = new RateLimitInfo($limit, $remaining, $reset);
        $count <= $limit ?: throw new TooManyRequestsException(max(1, $ttl));
    }

    private function checkAccessPermissions(): void
    {
        $this->accessPolicy->enforceAccess($this->firstResponder, $this->authenticationManager);
    }

    private function setTransactionAuthor(): void
    {
        $this->accessPolicy->setTransactionAuthor($this->firstResponder, $this->authenticationManager);
    }

    private function processResponse(): never
    {
        $response = $this->firstResponder->response;
        $this->delegate?->applicationDidFinishLaunching($this);
        $response->send();
    }

    private function handle(Throwable $throwable): never
    {
        if ($this->isTerminated) {
            exit;
        }
        $this->isTerminated = true;
        $responder = new ErrorResponder();
        $responder->handle($throwable);
    }

    /**
     * The singleton app instance.
     * @return Application
     */
    public static function shared(): Application
    {
        return static::$shared ??= new static();
    }

    /**
     * Executes the main process of the application and manages the workflow, including initialization, handling access permissions, and processing the response. Manages exceptions that occur during execution.
     *
     * @return never
     */
    public function run(): never
    {
        try {
            $this->bootstrapIfNeeded();
            $this->handlePreflightIfNeeded();
            $this->initializeApplication();
            $this->enforceRateLimitIfNeeded();
            $this->checkAccessPermissions();
            $this->setTransactionAuthor();
            $this->processResponse();
        } catch (Throwable $throwable) {
            $this->handle($throwable);
        }
    }

    /**
     * Terminates the receiver.
     */
    public function terminate(): never
    {
        exit();
    }
}
