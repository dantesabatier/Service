<?php

namespace Sabatier\Service;

use ReflectionClass;
use Sabatier\CoreData\PersistentContainer;
use Sabatier\CoreData\PersistentStoreDescription;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\DirectoryEnumerationOptions;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UserDefaults;
use Throwable;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\getallheaders;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\request_url;
use function Sabatier\Foundation\string_is_equal;
use const Sabatier\CoreData\PersistentHistoryTrackingKey;
use const Sabatier\CoreData\PersistentStoreRemoteChangeNotificationPostOptionKey;
use const Sabatier\Foundation\kCFBundleNameKey;

/**
 * An object that manages an app’s main url request and resources used by all of that app’s objects.
 */
class Application extends Responder
{
    private static ?Application $shared = null;
    public readonly URLRequest $request;
    public readonly PersistentContainer $persistentContainer;
    /** @var ApplicationDelegate|null The delegate of the app object. */
    public ?ApplicationDelegate $delegate = null;
    public ProtectionSpace $protectionSpace;
    private readonly PersistentSpace $persistentSpace;
    private readonly ResourceManager $resourceManager;

    final public function __construct()
    {
        parent::__construct();
        unset($this->request);
        unset($this->persistentContainer);
        unset($this->delegate);
        unset($this->protectionSpace);
        unset($this->persistentSpace);
        unset($this->resourceManager);
    }

    public function __get(string $name)
    {
        if ($name == "request") {
            $request = new URLRequest(new URL(request_url()));
            $request->httpMethod = $_SERVER["REQUEST_METHOD"] ?? HTTPRequestMethod::get;
            $request->allHTTPHeaderFields = new Dictionary(getallheaders());
            if ($value = $request->valueForHttpHeaderField("X-Http-Method-Override")) {
                $request->httpMethod = $value;
            }
            $this->$name = $request;
            return $this->$name;
        } elseif ($name == "persistentContainer") {
            $persistentContainer = new PersistentContainer(Bundle::main()->object(kCFBundleNameKey));
            if ($description = $persistentContainer->persistentStoreDescriptions->first()) {
                $description->setOptionForKey(UserDefaults::standard()->bool(PersistentHistoryTrackingKey), PersistentHistoryTrackingKey);
                $description->setOptionForKey(UserDefaults::standard()->bool(PersistentStoreRemoteChangeNotificationPostOptionKey), PersistentStoreRemoteChangeNotificationPostOptionKey);
            }
            $persistentContainer->loadPersistentStores(function (PersistentStoreDescription $description, ?Error $error): void {
                if ($error) {
                    fatal_error("Unable to load persistent stores: $error");
                }
            });
            $this->$name = $persistentContainer;
            return $this->$name;
        } elseif ($name == "delegate") {
            $delegate = null;
            if (($principalClass = Bundle::main()->principalClass) && isset(class_implements($principalClass)[ApplicationDelegate::class])) {
                /** @var class-string<ApplicationDelegate> $delegateClass */
                $delegateClass = $principalClass;
                if (is_subclass_of($delegateClass, ObjectClass::class)) {
                    $delegateClass::initialize();
                }
                $delegate = new $delegateClass();
            }
            $this->$name = $delegate;
            return $this->$name;
        } elseif ($name == "protectionSpace") {
            $this->$name = new JSONWebTokenProtectionSpace();
            return $this->$name;
        } elseif ($name == "persistentSpace") {
            $this->$name = new PersistentSpace();
            return $this->$name;
        } elseif ($name == "resourceManager") {
            $this->$name = new ResourceManager();
            return $this->$name;
        } else {
            return parent::__get($name);
        }
    }

    /**
     * The singleton app instance.
     * @return Application
     */
    public static function shared(): Application
    {
        if (static::$shared === null) {
            static::$shared = new static();
        }
        return static::$shared;
    }

    private function instantiateInitialResponder(): Responder
    {
        if ($delegate = $this->delegate) {
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
                    if (!class_exists($responderClass) || !is_subclass_of($responderClass, Responder::class)) {
                        continue;
                    }
                    $responder = new $responderClass();
                    if ($responder->isFirstResponder()) {
                        return $responder;
                    }
                }
            }
        }
        foreach ([$this->protectionSpace, $this->persistentSpace, $this->resourceManager] as $responder) {
            if ($responder->isFirstResponder()) {
                return $responder;
            }
        }
        if ($this->request->url->path == "/") {
            return $this;
        }
        throw new NotFoundException();
    }

    private function send(HTTPURLResponse $response, ?string $content): void
    {
        $isEmpty = match ($response->statusCode) {
            HTTPStatusCode::created, HTTPStatusCode::noContent, HTTPStatusCode::resetContent, HTTPStatusCode::notModified => true,
            default => $response instanceof BatchResponse ? $response->isEmpty : empty($content)
        };
        if ($isEmpty) {
            foreach (["Content-Type", "Content-Length"] as $key) {
                $response->allHeaderFields->removeValueForKey($key);
            }
        }
        header(sprintf("%s %s %s", $response->httpVersion, $response->statusCode, HTTPURLResponse::localizedString($response->statusCode)));
        if ($response instanceof BatchResponse) {
            flush();
            header_register_callback(function () use ($response) {
                foreach ($response->allHeaderFields as $key => $value) {
                    header(sprintf("%s: %s", $key, human_readable_value($value)));
                    flush();
                }
            });
            if ($isEmpty) {
                die();
            }
            ob_start();
            foreach ($response as $idx => $data) {
                echo $data;
                if (($idx + 1) < $response->count) {
                    echo "\r\n";
                }
                flush();
            }
            ob_end_flush();
            die();
        }
        foreach ($response->allHeaderFields as $key => $value) {
            header(sprintf("%s: %s", $key, human_readable_value($value)));
        }
        if ($isEmpty) {
            die();
        }
        ob_start();
        /** @noinspection SpellCheckingInspection */
        ob_start("ob_gzhandler");
        echo $content;
        ob_end_flush();
        header("Content-Length: " . ob_get_length());
        ob_end_flush();
    }

    public function run(): void
    {
        try {
            ProcessInfo::processInfo()->processName = $this->persistentContainer->name;
            $viewContext = $this->persistentContainer->viewContext;
            $viewContext->name = $this->persistentContainer->name;
            register_shutdown_function(function (): bool {
                $this->delegate?->applicationWillTerminate($this);
                return true;
            });
            $this->delegate?->applicationWillFinishLaunching($this);
            $responder = $this->instantiateInitialResponder();
            $responder->isProtectedContentAvailable = $this->isProtectedContentAvailable;
            if (!$responder->allowedMethods->containsElement($this->request->httpMethod)) {
                throw new MethodNotAllowedException();
            }
            if ($this->request->httpMethod != HTTPRequestMethod::options) {
                $protectionSpace = $this->protectionSpace;
                if (!$responder->isProtectedContentAvailable && !$responder->isEqual($protectionSpace) && !$protectionSpace->isProtectedContentAvailable) {
                    throw new UnauthorizedException();
                }
                if ($this->request->httpMethod != HTTPRequestMethod::get) {
                    $viewContext->transactionAuthor = $protectionSpace->username;
                }
            }
            $this->delegate?->applicationDidFinishLaunching($this);
            $response = $responder->response();
            $headerFields = $response->allHeaderFields;
            $headerFields["Content-Type"] = $responder->contentType;
            $headerFields["Content-Disposition"] = $responder->contentDisposition;
            if ($value = $this->request->valueForHttpHeaderField("Origin")) {
                $headerFields["Access-Control-Allow-Origin"] = $value;
                $headerFields["Access-Control-Allow-Credentials"] = true;
            }
            if ($value = $this->request->valueForHttpHeaderField("Access-Control-Request-Method")) {
                $headerFields["Access-Control-Allow-Methods"] = $value;
            }
            if ($value = $this->request->valueForHttpHeaderField("Access-Control-Request-Headers")) {
                $headerFields["Access-Control-Allow-Headers"] = $value;
            }
            $this->send($response, $responder->content);
        } catch (Throwable $throwable) {
            $response = $throwable instanceof InvalidRequestException ? new HTTPURLResponse($this->request->url, (int)$throwable->getCode(), null, $throwable instanceof UnauthorizedException ? new Dictionary(["WWW-Authenticate" => "{$this->protectionSpace->defaultAuthenticationMethod} realm=\"{$this->request->url->host}\""]) : null) : new HTTPURLResponse($this->request->url, HTTPStatusCode::internalServerError);
            $content = $this->delegate?->applicationWillFail($this, $response, $throwable);
            if ($content instanceof View) {
                $content = $content->render();
            }
            $this->send($response, $content);
        }
    }

    /**
     * Terminates the receiver.
     * @return never
     */
    public function terminate(): never
    {
        exit();
    }
}
