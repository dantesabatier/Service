<?php

namespace Sabatier\Service;

use Exception;
use JetBrains\PhpStorm\Immutable;
use Override;
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
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\Notification;
use Sabatier\Foundation\NotificationCenter;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UserDefaults;
use Throwable;
use function Sabatier\Foundation\getallheaders;
use function Sabatier\Foundation\request_url;
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
    /** @var string A notification that posts shortly before protected files are locked down and become inaccessible. */
    final public const string protectedDataWillBecomeUnavailableNotification = "protectedDataWillBecomeUnavailableNotification";
    /** @var string A notification that posts when the protected files become available for your code to access. */
    final public const string protectedDataDidBecomeAvailableNotification = "protectedDataDidBecomeAvailableNotification";
    private static ?Application $shared = null;
    public readonly URLRequest $request;
    /** @var ApplicationDelegate|null The delegate of the app object. */
    public ?ApplicationDelegate $delegate = null;
    public Session $session;
    public Authentication $authentication;
    public readonly PersistentContainer $persistentContainer;
    public readonly PersistentSpace $persistentSpace;
    public readonly ResourceManager $resourceManager;
    public readonly Preferences $preferences;
    public readonly Uploader $uploader;
    #[Immutable(Immutable::PRIVATE_WRITE_SCOPE)]
    public PersistentHistoryToken $persistentHistoryToken;

    final public function __construct()
    {
        parent::__construct();
        unset($this->request);
        unset($this->delegate);
        unset($this->session);
        unset($this->persistentContainer);
        unset($this->authentication);
        unset($this->persistentSpace);
        unset($this->resourceManager);
        unset($this->preferences);
        unset($this->uploader);
        unset($this->historyChanges);
        unset($this->persistentHistoryToken);
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function __get(string $name)
    {
        if ($name === "request") {
            $this->$name = $this->request();
            return $this->$name;
        }
        if ($name === "delegate") {
            $this->$name = $this->delegate();
            return $this->$name;
        }
        if ($name === "persistentContainer") {
            $this->$name = $this->persistentContainer();
            return $this->$name;
        }
        if ($name === "session") {
            $this->$name = new Session();
            return $this->$name;
        }
        if ($name === "authentication") {
            $this->$name = new Authentication();
            return $this->$name;
        }
        if ($name === "persistentSpace") {
            $this->$name = new PersistentSpace();
            return $this->$name;
        }
        if ($name === "resourceManager") {
            $this->$name = new ResourceManager();
            return $this->$name;
        }
        if ($name === "preferences") {
            $this->$name = new Preferences();
            return $this->$name;
        }
        if ($name === "uploader") {
            $this->$name = new Uploader();
            return $this->$name;
        }
        if ($name === "persistentHistoryToken") {
            $this->$name = UserDefaults::standard()->object(PersistentHistoryTokenKey) ? KeyedUnarchiver::unarchiveTopLevelObjectWithData(UserDefaults::standard()->object(PersistentHistoryTokenKey)) : new PersistentHistoryToken(new Dictionary([(string)$this->persistentContainer->persistentStoreCoordinator->persistentStores->first?->identifier => new Number(0)]));
            return $this->$name;
        }
        return parent::__get($name);
    }

    private function request(): URLRequest
    {
        $request = new URLRequest(new URL(request_url()));
        $request->allHTTPHeaderFields = new Dictionary(getallheaders());
        $request->httpMethod = $request->valueForHttpHeaderField("X-Http-Method-Override") ?? $_SERVER["REQUEST_METHOD"] ?? HTTPRequestMethod::get;
        $request->httpBody = match ($request->httpMethod) {
            HTTPRequestMethod::post, HTTPRequestMethod::put, HTTPRequestMethod::delete, HTTPRequestMethod::patch => (function () use ($request): ?string {
                $contentType = $request->valueForHttpHeaderField("Content-Type") ?? "text/plain";
                $mediaType = $contentType;
                if (str_contains($contentType, ";")) {
                    [$mediaType,] = explode(";", $contentType);
                }
                $httpBody = match ($mediaType) {
                    "application/x-www-form-urlencoded", "application/json" => file_get_contents("php://input"),
                    default => null
                };
                return empty($httpBody) ? null : $httpBody;
            })(),
            default => null
        };
        return $request;
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
            UserDefaults::standard()->setObject(KeyedArchiver::archivedData($persistentHistoryToken), PersistentHistoryTokenKey);
            $this->persistentHistoryToken = $persistentHistoryToken;
            $context = $this->persistentContainer->viewContext;
            $request = PersistentHistoryChangeRequest::deleteHistoryBeforeToken($persistentHistoryToken);
            $request->fetchRequest = PersistentHistoryTransaction::fetchRequest();
            $context->execute($request);
        });
        return $persistentContainer;
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
                if ($responder->isFirstResponder()) {
                    return $responder;
                }
            }
        }
        return null;
    }

    private function internalResponder(): ?Responder
    {
        return (new ArrayClass([$this->authentication, $this->persistentSpace, $this->resourceManager, $this->preferences, $this->uploader, new Home()]))->first(fn(Responder $responder): bool => $responder->isFirstResponder());
    }

    private function instantiateInitialResponder(): Responder
    {
        return $this->mainResponder() ?? $this->internalResponder() ?? throw new NotFoundException();
    }

    public function run(): never
    {
        try {
            $this->delegate?->applicationWillFinishLaunching($this);
            ProcessInfo::processInfo()->processName = $this->persistentContainer->name;
            $this->persistentContainer->viewContext->name = $this->persistentContainer->name;
            register_shutdown_function(function (): bool {
                $this->delegate?->applicationWillTerminate($this);
                return true;
            });
            $responder = $this->instantiateInitialResponder();
            $this->session->start();
            if (!$responder->isProtectedContentAvailable) {
                $responder->isProtectedContentAvailable = $this->isProtectedContentAvailable;
            }
            if (!$responder->isProtectedContentAvailable && !$this->authentication->isProtectedContentAvailable) {
                throw new UnauthorizedException();
            }
            if ($responder->isProtectedContentAvailable) {
                $this->isProtectedContentAvailable = $responder->isProtectedContentAvailable;
                NotificationCenter::default()->postNotificationName(Application::protectedDataDidBecomeAvailableNotification, $this);
            }
            $this->persistentContainer->viewContext->transactionAuthor = match ($this->request->httpMethod) {
                HTTPRequestMethod::post, HTTPRequestMethod::put, HTTPRequestMethod::patch, HTTPRequestMethod::delete => $this->authentication->authorization->user?->valueForKey("username"),
                default => null
            };
            $response = $responder->response();
            $this->session->commit();
            if ($responder->isProtectedContentAvailable) {
                $responder->isProtectedContentAvailable = false;
                $this->isProtectedContentAvailable = $responder->isProtectedContentAvailable;
                NotificationCenter::default()->postNotificationName(Application::protectedDataWillBecomeUnavailableNotification, $this);
            }
            $this->delegate?->applicationDidFinishLaunching($this);
            ResponseEmitter::from($response, $responder->content)->emit();
        } catch (Throwable $throwable) {
            ResponseEmitter::from($throwable)->emit();
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
