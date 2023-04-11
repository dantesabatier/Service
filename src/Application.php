<?php

namespace Sabatier\Service;

use ReflectionClass;
use Sabatier\CoreData\PersistentContainer;
use Sabatier\CoreData\PersistentStoreDescription;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\DirectoryEnumerationOptions;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPCookiePropertyKey;
use Sabatier\Foundation\Networking\HTTPCookieStringPolicy;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\Set;
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
use const Sabatier\Foundation\LocalizedDescriptionKey;
use const Sabatier\Foundation\LocalizedFailureReasonErrorKey;
use const Sabatier\Foundation\URLErrorBadServerResponse;
use const Sabatier\Foundation\URLErrorDomain;

/**
 * An object that manages an app’s main url request and resources used by all of that app’s objects.
 */
class Application extends Responder
{
    private static ?Application $shared = null;
    public readonly URLRequest $request;
    /** @var ApplicationDelegate|null The delegate of the app object. */
    public ?ApplicationDelegate $delegate = null;
    public Authentication $authentication;
    public readonly PersistentContainer $persistentContainer;
    private readonly PersistentSpace $persistentSpace;
    private readonly ResourceManager $resourceManager;

    final public function __construct()
    {
        parent::__construct();
        unset($this->request);
        unset($this->delegate);
        unset($this->persistentContainer);
        unset($this->authentication);
        unset($this->persistentSpace);
        unset($this->resourceManager);
    }

    /** @suppress PHP0418 */
    public function __get(string $name)
    {
        if ($name == "request") {
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
            $this->$name = $request;
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
        } elseif ($name == "authentication") {
            $this->$name = new Authentication();
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

    private function send(HTTPURLResponse $response, ?string $content, ?string $contentType = null, ?int $contentLength = null, ?string $contentDisposition = null, ArrayClass $allowedMethods = new ArrayClass()): never
    {
        $isEmpty = match ($response->statusCode) {
            HTTPStatusCode::created, HTTPStatusCode::noContent, HTTPStatusCode::resetContent, HTTPStatusCode::notModified => true,
            default => $response instanceof BatchResponse ? $response->isEmpty : empty($content)
        };
        $headerFields = $response->allHeaderFields;
        $headerFields["Content-Type"] = $contentType;
        $headerFields["Content-Length"] = $contentLength;
        $headerFields["Content-Disposition"] = $contentDisposition;
        if ($origin = $this->request->valueForHttpHeaderField("Origin")) {
            $headerFields["Access-Control-Allow-Origin"] = $origin;
            $headerFields["Access-Control-Allow-Credentials"] = true;
            $headerFields["Vary"] = "Origin";
        }
        if ($requestMethod = $this->request->valueForHttpHeaderField("Access-Control-Request-Method")) {
            if (!$allowedMethods->contains(fn(string $allowedMethod): bool => string_is_equal($allowedMethod, $requestMethod, CompareOptions::caseInsensitive))) {
                $allowedMethods->append($requestMethod);
            }
            $headerFields["Access-Control-Allow-Methods"] = $allowedMethods->join(", ");
        }
        if ($value = $this->request->valueForHttpHeaderField("Access-Control-Request-Headers")) {
            $requestHeaders = new ArrayClass(explode(",", $value));
            /** @var ArrayClass<string> $allowedHeaders */
            $allowedHeaders = new ArrayClass(["Content-Type", "Serialization", "Authorization"]);
            foreach ($requestHeaders as $requestHeader) {
                if (!$allowedHeaders->contains(fn(string $allowedHeader): bool => string_is_equal($allowedHeader, $requestHeader, CompareOptions::caseInsensitive))) {
                    $allowedHeaders->append($requestHeader);
                }
            }
            $headerFields["Access-Control-Allow-Headers"] = $allowedHeaders->join(", ");
        }
        if ($isEmpty) {
            $headerFields->removeAll(fn(mixed $e, string $k): bool => match ($k) {
                "Content-Type", "Content-Length", "Content-Disposition" => true,
                default => false
            });
        }
        header(sprintf("%s %s %s", $response->httpVersion, $response->statusCode, HTTPURLResponse::localizedString($response->statusCode)));
        if ($response instanceof BatchResponse) {
            flush();
            header_register_callback(function () use ($headerFields) {
                foreach ($headerFields as $key => $value) {
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
        foreach ($headerFields as $key => $value) {
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
        die();
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
                if (!class_exists($responderClass) || !is_subclass_of($responderClass, Responder::class)) {
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

    private function privateResponder(): ?Responder
    {
        foreach ([$this->authentication, $this->persistentSpace, $this->resourceManager] as $responder) {
            if ($responder->isFirstResponder()) {
                return $responder;
            }
        }
        $responder = new Home();
        if ($responder->isFirstResponder()) {
            return $responder;
        }
        return null;
    }

    private function instantiateInitialResponder(): Responder
    {
        if (!($responder = $this->mainResponder()) && !($responder = $this->privateResponder())) {
            throw new NotFoundException();
        }
        if (!$responder->isProtectedContentAvailable) {
            $responder->isProtectedContentAvailable = $this->isProtectedContentAvailable;
        }
        if ($this->request->httpMethod !== HTTPRequestMethod::options) {
            if (!$responder->isProtectedContentAvailable && !$responder instanceof Authentication && !$this->authentication->isProtectedContentAvailable) {
                throw new UnauthorizedException();
            }
            if ($this->request->httpMethod !== HTTPRequestMethod::get && $responder instanceof PersistentSpace) {
                $this->persistentContainer->viewContext->transactionAuthor = $this->authentication->credential?->user;
            }
        }
        return $responder;
    }

    public function run(): void
    {
        try {
            ProcessInfo::processInfo()->processName = $this->persistentContainer->name;
            $viewContext = $this->persistentContainer->viewContext;
            $viewContext->name = $this->persistentContainer->name;
            $delegate = $this->delegate;
            register_shutdown_function(function () use ($delegate): bool {
                $delegate?->applicationWillTerminate($this);
                return true;
            });
            $delegate?->applicationWillFinishLaunching($this);
            if ($this->request->httpMethod !== HTTPRequestMethod::options) {
                /** @psalm-suppress InvalidArgument */
                session_set_cookie_params([
                    HTTPCookiePropertyKey::lifetime => 60 * 60 * 8,
                    HTTPCookiePropertyKey::path => "/",
                    HTTPCookiePropertyKey::domain => $this->request->url->host,
                    HTTPCookiePropertyKey::secure => true,
                    HTTPCookiePropertyKey::httpOnly => true,
                    HTTPCookiePropertyKey::sameSitePolicy => HTTPCookieStringPolicy::sameSiteLax,
                ]);
                session_save_path(FileManager::default()->url(SearchPathDirectory::applicationSupportDirectory)->appendingPathComponent($this->persistentContainer->name)->path);
                session_start();
            }
            $responder = $this->instantiateInitialResponder();
            if ($this->request->httpMethod !== HTTPRequestMethod::options) {
                session_write_close();
            }
            $delegate?->applicationDidFinishLaunching($this);
            $this->send($responder->response(), $responder->content, $responder->contentType, $responder->contentLength, $responder->contentDisposition, $responder->allowedMethods);
        } catch (Throwable $throwable) {
            $response = $throwable instanceof InvalidRequestException ? new HTTPURLResponse($this->request->url, $throwable->getCode(), null, (function () use ($throwable): ?Dictionary {
                if (!$throwable instanceof UnauthorizedException) {
                    return null;
                }
                $authentication = $this->authentication;
                $realm = $authentication->request->url->host ?? "";
                $scheme = $authentication->scheme;
                $challenge = "$scheme->value realm=\"$realm\"";
                $challenge .= match ($scheme) {
                    AuthenticationScheme::digest => sprintf(", uri=\"%s\", qop=\"auth\", nonce=\"%s\", opaque=\"%s\"", $this->request->url->absoluteString, uniqid(), md5($realm)),
                    default => ""
                };
                return new Dictionary(["WWW-Authenticate" => $challenge]);
            })()) : new HTTPURLResponse($this->request->url, HTTPStatusCode::internalServerError);
            $userInfo = new Dictionary([LocalizedDescriptionKey => HTTPURLResponse::localizedString($response->statusCode)]);
            if ($failureReason = $throwable->getMessage()) {
                $userInfo[LocalizedFailureReasonErrorKey] = $failureReason;
            }
            $error = $this->delegate?->applicationWillPresentError($this, new Error(URLErrorDomain, URLErrorBadServerResponse, $userInfo));
            $this->send($response, $error?->localizedDescription);
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
