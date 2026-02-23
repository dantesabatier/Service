<?php

namespace Sabatier\Service;

use ErrorException;
use Exception;
use Override;
use Sabatier\CoreData\MergePolicy;
use Sabatier\CoreData\PersistentContainer;
use Sabatier\CoreData\PersistentStoreDescription;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Notification;
use Sabatier\Foundation\NotificationCenter;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\UserDefaults;
use Throwable;
use const Sabatier\CoreData\PersistentHistoryTrackingKey;
use const Sabatier\CoreData\PersistentStoreRemoteChange;
use const Sabatier\CoreData\PersistentStoreRemoteChangeNotificationPostOptionKey;
use const Sabatier\Foundation\kCFBundleNameKey;

/**
 * The central object that coordinates request handling, persistence,
 * session management, authentication, authorization, and high-level
 * application lifecycle events.
 *
 * The `Application` class acts as the root of the responder chain and
 * provides access to shared services such as the persistent container,
 * authentication and authorization managers, and the currently active
 * session. It is designed as a singleton accessed through `Application::shared()`.
 *
 * ## Responsibilities
 * - Initializes and configures the Core Data stack.
 * - Manages the application delegate lifecycle events.
 * - Sets up and maintains the user session.
 * - Resolves the first responder for each request.
 * - Applies access control policies and establishes the transaction author.
 * - Executes the main application run loop and handles any exceptions.
 *
 * ## Customization
 * Developers can customize:
 * - `authorizationCache` to use custom cache backends.
 * - `authorizationService` to override authorization logic.
 * - `accessPolicy` to define custom access-control behavior.
 *
 * ## Lifecycle
 * The `run()` method performs:
 * - Application initialization,
 * - Session initialization,
 * - Access checking,
 * - Transaction author assignment,
 * - Response processing.
 *
 * Any thrown exception is captured and delegated to an internal responder
 * that renders a safe, consistent error response.
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
    /** @var AuthorizationCache The authorization cache for storing authorization data. */
    public AuthorizationCache $authorizationCache {
        get => $this->authorizationCache ??= new InMemoryAuthorizationCache();
    }
    /** @var AuthorizationService The authorization service for managing user authorization. */
    #[Override]
    public AuthorizationService $authorizationService {
        get => $this->authorizationService ??= new AuthorizationService($this->authorizationResolver, $this->authorizationCache);
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
    /** @var AccessPolicy The access policy for enforcing access control. */
    #[Override]
    public AccessPolicy $accessPolicy {
        get => $this->accessPolicy ??= new DefaultAccessPolicy();
    }
    /** @var StaticResourcePolicy Policy used to determine how the application handles static resources. */
    public StaticResourcePolicy $staticResourcePolicy {
        get => $this->staticResourcePolicy ??= new DefaultStaticResourcePolicy();
    }
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
        $this->configureViewContext($persistentContainer);
        return $persistentContainer;
    }

    private function configurePersistentStoreDescriptions(PersistentContainer $persistentContainer): void
    {
        $description = $persistentContainer->persistentStoreDescriptions->first;
        if ($description) {
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

    private function configureViewContext(PersistentContainer $persistentContainer): void
    {
        $persistentContainer->viewContext->mergePolicy = MergePolicy::error();
    }

    private function addPersistentStoreObservers(PersistentContainer $persistentContainer): void
    {
        NotificationCenter::default()->addObserverForName(PersistentStoreRemoteChange, $persistentContainer->persistentStoreCoordinator, function (Notification $notification): void {
            $this->handlePersistentStoreRemoteChange($notification);
        });
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
        static::$shared ??= new static();
        return static::$shared;
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
