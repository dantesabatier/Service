<?php

namespace Sabatier\Service;

use Exception;
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
 * An object that manages an app's main url request and resources used by all of that app's objects.
 */
class Application extends Responder
{
    private static ?Application $shared = null;
    /** @var ApplicationDelegate|null The delegate of the app object. */
    private(set) ?ApplicationDelegate $delegate {
        get => $this->delegate ??= $this->initializeDelegate();
    }
    private(set) PersistentContainer $persistentContainer {
        get => $this->persistentContainer ??= $this->createPersistentContainer();
    }
    private(set) Session $session {
        get => $this->session ??= new Session();
    }
    private(set) Responder $firstResponder {
        get => $this->firstResponder ??= new FirstResponderResolver($this, $this->authenticationManager)->firstResponder;
    }
    public AuthorizationService $authorizationService {
        get => $this->authorizationService ??= new DefaultAuthorizationService();
    }
    public AuthenticationService $authenticationService {
        get => $this->authenticationService ??= new DefaultAuthenticationService();
    }
    public AuthenticationManager $authenticationManager {
        get => $this->authenticationManager ??= new DefaultAuthenticationManager();
    }
    public AccessPolicy $accessPolicy {
        get => $this->accessPolicy ??= new DefaultAccessPolicy();
    }
    private AccessControl $accessControl {
        get => $this->accessControl ??= new AccessControl($this->authorizationService, $this->authenticationManager, $this->accessPolicy);
    }

    private function initializeDelegate(): ?ApplicationDelegate
    {
        $principalClass = (string)Bundle::main()->principalClass;
        /** @var array<string, class-string> $implementations */
        $implementations = class_implements($principalClass);
        if (!isset($implementations[ApplicationDelegate::class])) {
            return null;
        }
        /** @var class-string<ApplicationDelegate> $delegateClass */
        $delegateClass = $principalClass;
        $this->initializeDelegateClass($delegateClass);
        return new $delegateClass();
    }

    /**
     * @param class-string<ApplicationDelegate> $delegateClass
     */
    private function initializeDelegateClass(string $delegateClass): void
    {
        if (is_subclass_of($delegateClass, ObjectClass::class)) {
            $delegateClass::initialize();
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

    private function initializeApplication(): void
    {
        $delegate = $this->delegate;
        $delegate?->applicationWillFinishLaunching($this);
        $persistentContainer = $this->persistentContainer;
        $viewContext = $persistentContainer->viewContext;
        $viewContext->name = $persistentContainer->name;
        ProcessInfo::processInfo()->processName = $persistentContainer->name;
        register_shutdown_function(function () use ($delegate): bool {
            $delegate?->applicationWillTerminate($this);
            return true;
        });
    }

    private function initializeSession(): void
    {
        $this->session->start();
    }

    private function checkAccessPermissions(): void
    {
        $this->accessControl->validateAccess($this->firstResponder);
    }

    private function setTransactionAuthor(): void
    {
        $this->accessControl->setTransactionAuthor($this->firstResponder);
    }

    private function processResponse(): never
    {
        $response = $this->firstResponder->response;
        $this->session->commit();
        $this->delegate?->applicationDidFinishLaunching($this);
        $response->send();
    }

    private function handleException(Throwable $throwable): never
    {
        $responder = new Thrower();
        $responder->throw($throwable);
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
            $this->initializeApplication();
            $this->initializeSession();
            $this->checkAccessPermissions();
            $this->setTransactionAuthor();
            $this->processResponse();
        } catch (Throwable $throwable) {
            $this->handleException($throwable);
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
