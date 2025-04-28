<?php

namespace Sabatier\Service;

use ReflectionClass;
use Sabatier\CoreData\PersistentContainer;
use Sabatier\CoreData\PersistentHistoryChangeRequest;
use Sabatier\CoreData\PersistentHistoryToken;
use Sabatier\CoreData\PersistentHistoryTransaction;
use Sabatier\CoreData\PersistentStoreDescription;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\DirectoryEnumerationOptions;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Notification;
use Sabatier\Foundation\NotificationCenter;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\UserDefaults;
use Throwable;
use function Sabatier\Foundation\string_is_equal;
use const Sabatier\CoreData\PersistentHistoryTokenKey;
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
        get {
            if (!isset($this->delegate)) {
                if (($principalClass = Bundle::main()->principalClass) && isset(class_implements($principalClass)[ApplicationDelegate::class])) {
                    /** @var class-string<ApplicationDelegate> $delegateClass */
                    $delegateClass = $principalClass;
                    if (is_subclass_of($delegateClass, ObjectClass::class)) {
                        $delegateClass::initialize();
                    }
                    $this->delegate = new $delegateClass();
                }
                $this->delegate ??= null;
            }
            return $this->delegate;
        }
    }
    private(set) PersistentContainer $persistentContainer {
        get {
            if (!isset($this->persistentContainer)) {
                $persistentContainer = new PersistentContainer(Bundle::main()->object(kCFBundleNameKey));
                if ($description = $persistentContainer->persistentStoreDescriptions->first) {
                    $description->setOptionForKey(UserDefaults::standard()->bool(PersistentHistoryTrackingKey), PersistentHistoryTrackingKey);
                    $description->setOptionForKey(UserDefaults::standard()->bool(PersistentStoreRemoteChangeNotificationPostOptionKey), PersistentStoreRemoteChangeNotificationPostOptionKey);
                }
                $persistentContainer->loadPersistentStores(function (PersistentStoreDescription $description, ?Error $error): void {
                    if ($error) {
                        throw new InternalInconsistencyException(error: $error);
                    }
                });
                NotificationCenter::default()->addObserverForName(PersistentStoreRemoteChange, $persistentContainer->persistentStoreCoordinator, function (Notification $notification): void {
                    /** @var Dictionary<mixed> $userInfo */
                    $userInfo = $notification->userInfo;
                    /** @var PersistentHistoryToken $persistentHistoryToken */
                    $persistentHistoryToken = $userInfo[PersistentHistoryTokenKey];
                    $context = $this->persistentContainer->viewContext;
                    $request = PersistentHistoryChangeRequest::deleteHistoryBeforeToken($persistentHistoryToken);
                    $request->fetchRequest = PersistentHistoryTransaction::fetchRequest();
                    $context->execute($request);
                });
                $this->persistentContainer = $persistentContainer;
            }
            return $this->persistentContainer;
        }
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

    private function customResponder(): ?Responder
    {
        if (!($delegate = $this->delegate)) {
            return null;
        }
        $initialResponder = null;
        $namespaceName = new ReflectionClass($delegate)->getNamespaceName();
        $directories = [RespondersDirectory, ViewControllersDirectory];
        $fileManager = FileManager::default();
        $baseURL = Bundle::main()->bundleURL->appendingPathComponent("src");
        foreach ($directories as $directory) {
            $directoryURL = $baseURL->appendingPathComponent($directory);
            if (!$fileManager->fileExists($directoryURL->path)) {
                continue;
            }
            $urls = $fileManager->contentsOfDirectory($directoryURL, null, DirectoryEnumerationOptions::skipsHiddenFiles);
            foreach ($urls as $url) {
                if (!string_is_equal($url->pathExtension, "php", CompareOptions::caseInsensitive)) {
                    continue;
                }
                $responderClass = "$namespaceName\\$directoryURL->lastPathComponent\\{$fileManager->displayName($url->path)}";
                if (class_exists($responderClass) && is_subclass_of($responderClass, Responder::class)) {
                    $firstResponder = new $responderClass();
                    $firstResponder->nextResponder = $initialResponder;
                    $initialResponder = $firstResponder;
                }
            }
        }
        return $initialResponder;
    }

    private function initialResponder(): ?Responder
    {
        $initialResponder = $this->customResponder();
        $persistentSpace = new PersistentSpace();
        $resourceManager = new ResourceManager();
        $preferences = new Preferences();
        $uploader = new Uploader();
        $downloader = new Downloader();
        $home = new Home();
        $persistentSpace->nextResponder = $resourceManager;
        $resourceManager->nextResponder = $preferences;
        $preferences->nextResponder = $uploader;
        $uploader->nextResponder = $downloader;
        $downloader->nextResponder = $home;
        $accessManager = $this->accessManager;
        $initialResponder ??= $accessManager;
        if (!$initialResponder instanceof AccessManager) {
            $initialResponder->nextResponder = $accessManager;
        }
        $accessManager->nextResponder = $persistentSpace;
        return $initialResponder;
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
        throw new NotFoundException();
    }

    private function resolveFirstResponder(): Responder
    {
        return match ($this->request->httpMethod) {
            HTTPRequestMethod::options => $this,
            default => $this->findFirstResponder()
        };
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

    public function run(): never
    {
        try {
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
            $responder = $this->firstResponder;
            $session = $this->session;
            $session->start();
            $accessManager = $this->accessManager;
            if (!$responder->isProtectedContentAvailable && !$accessManager->isProtectedContentAvailable) {
                if ($accessManager->authentication->isValid) {
                    throw new ForbiddenException(match ($this->request->httpMethod) {
                        HTTPRequestMethod::get => "You don't have permission to access this resource.",
                        default => "You don't have permission to perform this action."
                    });
                }
                throw new UnauthorizedException();
            }
            $viewContext->transactionAuthor = match ($this->request->httpMethod) {
                HTTPRequestMethod::post, HTTPRequestMethod::put, HTTPRequestMethod::patch, HTTPRequestMethod::delete => $accessManager->authentication->user?->username,
                default => null
            };
            $response = $responder->response;
            $session->commit();
            $delegate?->applicationDidFinishLaunching($this);
            $response->send();
        } catch (Throwable $throwable) {
            $responder = new Thrower();
            $responder->throw($throwable);
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
