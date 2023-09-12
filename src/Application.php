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
    public Session $session;
    public Authentication $authentication;
    public readonly PersistentContainer $persistentContainer;
    private readonly PersistentSpace $persistentSpace;
    private readonly ResourceManager $resourceManager;

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
        } elseif ($name == "session") {
            $this->$name = new Session();
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

    private function send(HTTPURLResponse $response, ?string $content, ?string $contentType = null, ?int $contentLength = null, ?string $contentDisposition = null): never
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
        if ($value = $this->request->valueForHttpHeaderField("Access-Control-Request-Method")) {
            $headerFields["Access-Control-Allow-Methods"] = $value;
        }
        if ($value = $this->request->valueForHttpHeaderField("Access-Control-Request-Headers")) {
            $headerFields["Access-Control-Allow-Headers"] = $value;
        }
        if ($isEmpty) {
            $headerFields->removeAll(fn(mixed $e, string $k): bool => match ($k) {
                "Content-Type", "Content-Length", "Content-Disposition" => true,
                default => false
            });
        }
        foreach (["Expires", "Cache-Control", "Pragma"] as $header) {
            header_remove($header);
        }
        header(sprintf("%s %s %s", $response->httpVersion, $response->statusCode, HTTPURLResponse::localizedString($response->statusCode)));
        if ($response instanceof BatchResponse) {
            flush();
            header_register_callback(function () use ($headerFields): void {
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

    private function internalResponder(): ?Responder
    {
        return (new ArrayClass([$this->authentication, $this->persistentSpace, $this->resourceManager, new Home()]))->first(fn(Responder $responder): bool => $responder->isFirstResponder());
    }

    private function instantiateInitialResponder(): Responder
    {
        if (!($responder = $this->mainResponder()) && !($responder = $this->internalResponder())) {
            throw new NotFoundException();
        }
        if (!$responder->isProtectedContentAvailable) {
            $responder->isProtectedContentAvailable = $this->isProtectedContentAvailable;
        }
        if ($responder === $this->authentication) {
            return $responder;
        }
        if (!$responder->isProtectedContentAvailable && !$this->authentication->isProtectedContentAvailable) {
            throw new UnauthorizedException();
        }
        $this->persistentContainer->viewContext->transactionAuthor = match ($this->request->httpMethod) {
            HTTPRequestMethod::post, HTTPRequestMethod::put, HTTPRequestMethod::patch, HTTPRequestMethod::delete => $this->session->valueForKey("user"),
            default => null
        };
        return $responder;
    }

    public function run(): void
    {
        try {
            $delegate = $this->delegate;
            ProcessInfo::processInfo()->processName = $this->persistentContainer->name;
            $this->persistentContainer->viewContext->name = $this->persistentContainer->name;
            register_shutdown_function(function () use ($delegate): bool {
                $delegate?->applicationWillTerminate($this);
                return true;
            });
            $delegate?->applicationWillFinishLaunching($this);
            $responder = match ($this->request->httpMethod) {
                HTTPRequestMethod::options => $this,
                default => (function (): Responder {
                    $session = $this->session;
                    $session->start();
                    $responder = $this->instantiateInitialResponder();
                    $session->commit();
                    return $responder;
                })()
            };
            $delegate?->applicationDidFinishLaunching($this);
            $this->send($responder->response(), $responder->content, $responder->contentType, $responder->contentLength, $responder->contentDisposition);
        } catch (Throwable $throwable) {
            $response = $throwable instanceof InvalidRequestException ? new HTTPURLResponse($this->request->url, $throwable->getCode(), null, $throwable instanceof UnauthorizedException ? new Dictionary(["WWW-Authenticate" => "{$this->authentication->authorization->scheme->value} realm=\"{$this->request->url->host}\"" . match ($this->authentication->authorization->scheme) {
                    AuthenticationScheme::digest => sprintf(", uri=\"%s\", algorithm=\"%s\", nonce=\"%s\", qop=\"%s\", opaque=\"%s\"", $this->request->url->path, "SHA-256", ProcessInfo::processInfo()->globallyUniqueString, "auth", base64_encode((string)$this->request->url->host)),
                    default => ""
                }]) : null) : new HTTPURLResponse($this->request->url, HTTPStatusCode::internalServerError);
            $error = $this->delegate?->applicationWillPresentError($this, new Error(URLErrorDomain, URLErrorBadServerResponse, new Dictionary([LocalizedDescriptionKey => HTTPURLResponse::localizedString($response->statusCode), LocalizedFailureReasonErrorKey => (string)$throwable])));
            $content = $error ? sprintf("%s. %s", $error->localizedDescription, $error->localizedFailureReason ?? "($error->domain error $error->code.)") : null;
            $this->send($response, $content);
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
