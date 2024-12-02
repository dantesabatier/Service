<?php

namespace Sabatier\Service;

use ReflectionClass;
use Sabatier\CoreData\PersistentContainer;
use Sabatier\CoreData\PersistentHistoryChangeRequest;
use Sabatier\CoreData\PersistentHistoryToken;
use Sabatier\CoreData\PersistentHistoryTransaction;
use Sabatier\CoreData\PersistentStoreDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\DirectoryEnumerationOptions;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\InternalInconsistencyException;
use Sabatier\Foundation\KeyedArchiver;
use Sabatier\Foundation\KeyedUnarchiver;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Notification;
use Sabatier\Foundation\NotificationCenter;
use Sabatier\Foundation\Number;
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
    public ?ApplicationDelegate $delegate {
        get => $this->delegate ??= $this->delegate();
    }
    public PersistentContainer $persistentContainer {
        get => $this->persistentContainer ??= $this->persistentContainer();
    }
    public Session $session {
        get => $this->session ??= new Session();
    }
    public AccessManager $accessManager {
        get => $this->accessManager ??= new AccessManager();
    }
    public PersistentSpace $persistentSpace {
        get => $this->persistentSpace ??= new PersistentSpace();
    }
    public ResourceManager $resourceManager {
        get => $this->resourceManager ??= new ResourceManager();
    }
    public Preferences $preferences {
        get => $this->preferences ??= new Preferences();
    }
    public Uploader $uploader {
        get => $this->uploader ??= new Uploader();
    }
    private(set) PersistentHistoryToken $persistentHistoryToken {
        get => UserDefaults::standard()->object(PersistentHistoryTokenKey) ? KeyedUnarchiver::unarchiveTopLevelObjectWithData(UserDefaults::standard()->object(PersistentHistoryTokenKey)) : new PersistentHistoryToken(new Dictionary([(string)$this->persistentContainer->persistentStoreCoordinator->persistentStores->first?->identifier => new Number(0)]));
        set {
            UserDefaults::standard()->setObject(KeyedArchiver::archivedData($value), PersistentHistoryTokenKey);
        }
    }
    private(set) ?Responder $firstResponder {
        get => $this->firstResponder ??= $this->mainResponder() ?? $this->internalResponder();
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

    private function delegate(): ?ApplicationDelegate
    {
        if (($principalClass = Bundle::main()->principalClass) && isset(class_implements($principalClass)[ApplicationDelegate::class])) {
            /** @var class-string<ApplicationDelegate> $delegateClass */
            $delegateClass = $principalClass;
            if (is_subclass_of($delegateClass, ObjectClass::class)) {
                $delegateClass::initialize();
            }
            return new $delegateClass();
        }
        return null;
    }

    private function persistentContainer(): PersistentContainer
    {
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
            $this->persistentHistoryToken = $persistentHistoryToken;
            $context = $this->persistentContainer->viewContext;
            $request = PersistentHistoryChangeRequest::deleteHistoryBeforeToken($this->persistentHistoryToken);
            $request->fetchRequest = PersistentHistoryTransaction::fetchRequest();
            $context->execute($request);
        });
        return $persistentContainer;
    }

    private function mainResponder(): ?Responder
    {
        if (!($delegate = $this->delegate)) {
            return null;
        }
        $reflectionClass = new ReflectionClass($delegate);
        $namespaceName = $reflectionClass->getNamespaceName();
        $fileManager = FileManager::default();
        $baseURL = Bundle::main()->bundleURL->appendingPathComponent("src");
        $directories = ["Responders", "ViewControllers"];
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
                $filePath = $url->path;
                /** @psalm-suppress UnresolvableInclude */
                require_once $filePath;
                $responderClass = "$namespaceName\\$directoryURL->lastPathComponent\\{$fileManager->displayName($filePath)}";
                if (!class_exists($responderClass)) {
                    continue;
                }
                if (!is_subclass_of($responderClass, Responder::class)) {
                    continue;
                }
                $responder = new $responderClass();
                if ($responder->isFirstResponder) {
                    return $responder;
                }
            }
        }
        return null;
    }

    private function internalResponder(): ?Responder
    {
        return new ArrayClass([$this->accessManager, $this->persistentSpace, $this->resourceManager, $this->preferences, $this->uploader, new Home()])->first(fn(Responder $responder): bool => $responder->isFirstResponder);
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
            $responder = $this->firstResponder ?? match ($this->request->httpMethod) {
                HTTPRequestMethod::options => $this,
                default => throw new NotFoundException()
            };
            $session = $this->session;
            $session->start();
            $accessManager = $this->accessManager;
            if (!$responder->isProtectedContentAvailable && !$accessManager->isProtectedContentAvailable) {
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
            $responder->throw($throwable, $this->accessManager->scheme);
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
