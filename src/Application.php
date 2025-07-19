<?php

namespace Sabatier\Service;

use Exception;
use ReflectionClass;
use Sabatier\CoreData\PersistentContainer;
use Sabatier\CoreData\PersistentStoreDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\DirectoryEnumerationOptions;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\FlattenSequence;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Notification;
use Sabatier\Foundation\NotificationCenter;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UserDefaults;
use Throwable;
use function Sabatier\Foundation\string_is_equal;
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
    public AuthorizationService $authorizationService {
        get => $this->authorizationService ??= new DefaultAuthorizationService();
    }
    private(set) PersistentContainer $persistentContainer {
        get => $this->persistentContainer ??= $this->createPersistentContainer();
    }
    private(set) Session $session {
        get => $this->session ??= new Session();
    }
    private(set) Responder $firstResponder {
        get => $this->firstResponder ??= $this->resolveFirstResponder();
    }
    private AccessManager $accessManager {
        get => $this->accessManager ??= new AccessManager();
    }

    private function initializeDelegate(): ?ApplicationDelegate
    {
        $principalClass = (string)Bundle::main()->principalClass;
        /** @var array<string, class-string> $implements */
        $implements = class_implements($principalClass);
        if (!isset($implements[ApplicationDelegate::class])) {
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
            if ($error !== null) {
                throw new InternalInconsistencyException(error: $error);
            }
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

    /**
     * @param string $namespaceName
     * @param URL $directoryURL
     * @param URL $fileURL
     * @return class-string<Responder>
     */
    private function buildClassName(string $namespaceName, URL $directoryURL, URL $fileURL): string
    {
        $directoryComponent = $directoryURL->lastPathComponent;
        $fileNameWithoutExtension = FileManager::default()->displayName($fileURL->path);
        /** @var class-string<Responder> */
        return "$namespaceName\\$directoryComponent\\$fileNameWithoutExtension";
    }

    private function isValidResponderClass(string $className): bool
    {
        if (!class_exists($className) || !is_subclass_of($className, Responder::class)) {
            return false;
        }
        $reflectionClass = new ReflectionClass($className);
        return $reflectionClass->isInstantiable();
    }

    /**
     * @param URL $directoryURL
     * @return ArrayClass<URL>
     */
    private function filteredFileURLs(URL $directoryURL): ArrayClass
    {
        return FileManager::default()->contentsOfDirectory($directoryURL, null, DirectoryEnumerationOptions::skipsHiddenFiles)->filter(fn(URL $url): bool => string_is_equal($url->pathExtension, "php", CompareOptions::caseInsensitive));
    }

    /**
     * @param string $namespaceName
     * @param URL $directoryURL
     * @return ArrayClass<class-string<Responder>>|null
     */
    private function responderClassesFromDirectory(string $namespaceName, URL $directoryURL): ?ArrayClass
    {
        if (!FileManager::default()->fileExists($directoryURL->path)) {
            return null;
        }
        return $this->filteredFileURLs($directoryURL)->map(fn(URL $fileURL): string => $this->buildClassName($namespaceName, $directoryURL, $fileURL))->filter(fn(string $className): bool => $this->isValidResponderClass($className));
    }


    /**
     * @param string $namespaceName
     * @return FlattenSequence<class-string<Responder>>
     */
    private function discoverResponderClasses(string $namespaceName): FlattenSequence
    {
        $directoryURL = Bundle::main()->bundleURL->appendingPathComponent("src");
        /** @var FlattenSequence<class-string<Responder>> */
        return new ArrayClass([RespondersDirectory, ViewControllersDirectory])->compactMap(fn(string $directoryName): ?ArrayClass => $this->responderClassesFromDirectory($namespaceName, $directoryURL->appendingPathComponent($directoryName)))->joined();
    }

    /**
     * @param ArrayClass<Responder> $responders
     * @return Responder
     */
    private function buildResponderChain(ArrayClass $responders): Responder
    {
        $previous = null;
        foreach ($responders as $responder) {
            if ($previous) {
                $previous->nextResponder = $responder;
            }
            $previous = $responder;
        }
        return $responders[0];
    }

    private function mergeChains(?Responder $responder1, ?Responder $responder2): ?Responder
    {
        if (!$responder1) {
            return $responder2;
        }
        $last = $responder1;
        while ($last->nextResponder) {
            $last = $last->nextResponder;
        }
        $last->nextResponder = $responder2;
        return $responder1;
    }

    private function customResponder(): ?Responder
    {
        if (!($delegate = $this->delegate)) {
            return null;
        }
        $namespaceName = new ReflectionClass($delegate)->getNamespaceName();
        $responderClasses = $this->discoverResponderClasses($namespaceName);
        /** @var ArrayClass<Responder> $responders */
        $responders = $responderClasses->map(
        /**
         * @param class-string<Responder> $responderClass
         */
            fn(string $responderClass): Responder => new $responderClass());
        if ($responders->isEmpty) {
            return null;
        }
        return $this->buildResponderChain($responders);
    }

    private function initialResponder(): ?Responder
    {
        /** @psalm-suppress InvalidArgument */
        return $this->mergeChains($this->customResponder(), $this->buildResponderChain(new ArrayClass([$this->accessManager, new PersistentSpace(), new ResourceManager(), new Preferences(), new Uploader(), new Downloader(), new Home()])));
    }

    private function findFirstResponder(): Responder
    {
        $responder = $this->initialResponder();
        while ($responder) {
            if ($responder->isFirstResponder) {
                return $responder;
            }
            $responder = $responder->nextResponder;
        }
        throw new NotFoundException("The requested URL was not found on this server {$this->request->url}");
    }

    private function resolveFirstResponder(): Responder
    {
        return $this->request->isPreflight ? $this : $this->findFirstResponder();
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
        if ($this->isProtectedContentAvailable) {
            $this->accessManager->isProtectedContentAvailable = true;
        }
        if (!$this->request->isPreflight) {
            $user = $this->accessManager->authentication->user;
            if ($user instanceof Authorizable) {
                $resource = $this->request->url->lastPathComponent;
                $action = match ($this->request->httpMethod) {
                    HTTPRequestMethod::head, HTTPRequestMethod::get => AuthorizationType::read,
                    HTTPRequestMethod::post => AuthorizationType::create,
                    HTTPRequestMethod::put, HTTPRequestMethod::patch => AuthorizationType::update,
                    HTTPRequestMethod::delete => AuthorizationType::delete,
                    default => throw new MethodNotAllowedException()
                };
                $this->authorizationService->authorize($user, $resource, $action, $this->managedObjectContext);
            }
        }
        if (!$this->firstResponder->isProtectedContentAvailable && !$this->accessManager->isProtectedContentAvailable) {
            if ($this->accessManager->authentication->isValid) {
                throw new ForbiddenException(match ($this->request->httpMethod) {
                    HTTPRequestMethod::get => "You don't have permission to access this resource.",
                    default => "You don't have permission to perform this action."
                });
            }
            throw new UnauthorizedException();
        }
    }

    private function setTransactionAuthor(): void
    {
        $this->persistentContainer->viewContext->transactionAuthor = match ($this->request->httpMethod) {
            HTTPRequestMethod::post, HTTPRequestMethod::put, HTTPRequestMethod::patch, HTTPRequestMethod::delete => $this->accessManager->authentication->user?->username,
            default => null
        };
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
