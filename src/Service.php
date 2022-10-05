<?php

namespace Sabatier\Service;

use Exception;
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
use Sabatier\Foundation\HTTPRequestMethod;
use Sabatier\Foundation\HTTPStatusCode;
use Sabatier\Foundation\HTTPURLResponse;
use Sabatier\Foundation\Number;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLRequest;
use Throwable;

use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\string_is_equal;

use const Sabatier\CoreData\PersistentHistoryTrackingKey;
use const Sabatier\CoreData\PersistentStoreRemoteChangeNotificationPostOptionKey;
use const Sabatier\Foundation\kCFBundleNameKey;

/**
 * Class Service
 * @package Sabatier\Service
 * @property-read ArrayClass<Endpoint> $endpoints
 */
class Service extends ObjectClass
{
    /** @internal */
    public static int $debugDefault = 0;
    public readonly URLRequest $request;
    public readonly PersistentContainer $persistentContainer;
    /** @var Dictionary<Endpoint> */
    public readonly Dictionary $endpointsByRoute;
    /** @var Dictionary<mixed>|null */
    public readonly ?Dictionary $serialization;
    public readonly Authentication $authentication;
    public readonly Authorization $authorization;
    public ?ServiceDelegate $delegate = null;

    public function __construct()
    {
        unset($this->request);
        unset($this->persistentContainer);
        unset($this->endpointsByRoute);
        unset($this->serialization);
        unset($this->authentication);
        unset($this->authorization);

        self::$debugDefault = (new Number(ProcessInfo::processInfo()->environment['SERVICE_DEBUG_LEVEL'] ?? 0))->intValue;
    }

    public function __get(string $name)
    {
        if ($name == 'request') {
            $url = new URL(build_request_url());
            $request = new URLRequest($url);
            $request->httpMethod = $_SERVER['REQUEST_METHOD'];
            $request->allHTTPHeaderFields = new Dictionary(getallheaders());
            $contents = file_get_contents('php://input');
            if (empty($contents)) {
                $contents = "[]";
            }
            /** @var array<string, mixed> $content */
            $content = empty($_FILES) ? json_decode($contents, true) : $_FILES;
            if (empty($content)) {
                parse_str($contents, $content);
                if (empty($content)) {
                    $content = $_REQUEST;
                }
            }
            if (!empty($content)) {
                $request->httpBody = json_encode($content);
            }
            $this->$name = $request;
            return $this->$name;
        } elseif ($name == 'persistentContainer') {
            $persistentContainer = new PersistentContainer(Bundle::main()->object(kCFBundleNameKey));
            if ($description = $persistentContainer->persistentStoreDescriptions->first()) {
                $description->setOptionForKey(false, PersistentHistoryTrackingKey);
                $description->setOptionForKey(false, PersistentStoreRemoteChangeNotificationPostOptionKey);
            }
            $persistentContainer->loadPersistentStores(function (PersistentStoreDescription $description, ?Error $error): void {
                if ($error) {
                    fatal_error("Unable to load persistent stores: $error");
                }
            });
            $this->$name = $persistentContainer;
            return $this->$name;
        } elseif ($name == 'endpointsByRoute') {
            $bundle = Bundle::main();
            /** @noinspection PhpUnhandledExceptionInspection */
            $principalClass = $bundle->principalClass ?? fatal_error("Unable to load bundle's principal class");
            /** @noinspection PhpUnhandledExceptionInspection */
            $reflectionClass = new ReflectionClass($principalClass);
            $namespaceName = $reflectionClass->getNamespaceName();
            /** @var Dictionary<Endpoint> $endpointsByRoute */
            $endpointsByRoute = new Dictionary();
            $register = /** @param class-string<Endpoint> $endpointClass */
                function (string $endpointClass) use ($endpointsByRoute): void {
                    /** @psalm-suppress UnsafeInstantiation */
                    $endpoint = new $endpointClass($this);
                    $endpointsByRoute[$endpoint->route()] = $endpoint; // @phpstan-ignore-line
                };
            $register(Authenticate::class);
            $register(Me::class);
            $register(Home::class);
            $register(Logout::class);
            $pluginsURL = $bundle->bundleURL->appendingPathComponent('src')->appendingPathComponent('Plugins');
            $fileManager = FileManager::default();
            if ($fileManager->fileExists($pluginsURL->path)) {
                $urls = $fileManager->contentsOfDirectory($pluginsURL, null, DirectoryEnumerationOptions::skipsHiddenFiles);
                foreach ($urls as $url) {
                    if (string_is_equal($url->pathExtension, 'php', CompareOptions::caseInsensitive)) {
                        $path = $url->path;
                        $fileName = pathinfo($path, PATHINFO_FILENAME);
                        /** @psalm-suppress UnresolvableInclude */
                        require_once $path;
                        $endpointClass = "$namespaceName\\$pluginsURL->lastPathComponent\\$fileName";
                        if (class_exists($endpointClass) && is_subclass_of($endpointClass, Endpoint::class)) {
                            $register($endpointClass);
                        }
                    }
                }
            }
            $this->$name = $endpointsByRoute;
            return $this->$name;
        } elseif ($name == 'endpoints') {
            return $this->endpointsByRoute->values;
        } elseif ($name == 'serialization') {
            $this->$name = (($string = $this->request->valueForHttpHeaderField($name)) && ($array = json_decode($string, true))) ? Dictionary::dictionaryWithArray($array) : null;
            return $this->$name;
        } elseif ($name == 'authentication') {
            $this->$name = new Authentication($this);
            return $this->$name;
        } elseif ($name == 'authorization') {
            $this->$name = new Authorization($this);
            return $this->$name;
        } else {
            return $this->valueForUndefinedKey($name);
        }
    }

    private function send(HTTPURLResponse $response, ?string $content): void
    {
        header(sprintf("%s %s %s", $response->httpVersion, $response->statusCode, HTTPURLResponse::localizedString($response->statusCode)));
        if ($response instanceof BatchResponse) {
            flush();
            header_register_callback(function () use ($response) {
                foreach ($response->allHeaderFields as $key => $value) {
                    header(sprintf("%s: %s", $key, human_readable_value($value)));
                    flush();
                }
            });
            ob_start();
            if ($response->isEmpty()) {
                echo $content ?? '';
            } else {
                $count = $response->count();
                foreach ($response as $idx => $data) {
                    echo $data;
                    if (($idx + 1) < $count) {
                        echo "\r\n";
                    }
                    flush();
                }
            }
        } else {
            foreach ($response->allHeaderFields as $key => $value) {
                if (!string_is_equal($key, 'Content-Length', CompareOptions::caseInsensitive)) {
                    header(sprintf("%s: %s", $key, human_readable_value($value)));
                }
            }
            ob_start();
            /** @noinspection SpellCheckingInspection */
            ob_start("ob_gzhandler");
            echo $content ?? '';
            ob_end_flush();
            header('Content-Length: ' . ob_get_length());
        }
        ob_end_flush();
    }

    /**
     * @throws Exception
     */
    private function validate(Endpoint $endpoint): bool
    {
        if ($endpoint->isSecure()) {
            $authentication = $this->authentication;
            if ($authentication->scheme != AuthenticationScheme::bearer) {
                if (self::$debugDefault) {
                    error_log(sprintf("%s %s(%s) invalid authentication scheme", self::class, __FUNCTION__, $endpoint->name()));
                }
                return false;
            }
            $authorization = $this->authorization;
            if (!($token = $authorization->token)) {
                if ($endpoint instanceof Authenticate) {
                    $user = $authorization->user;
                    $credential = $authorization->credential;
                    return ($password = $credential?->password) && ($hash = $user?->valueForKey('password')) && password_verify($password, $hash);
                }
                if (self::$debugDefault) {
                    error_log(sprintf("%s %s(%s) token cannot be null", self::class, __FUNCTION__, $endpoint->name()));
                }
                return false;
            }
            return $token->isValid;
        }
        return true;
    }

    public function run(): void
    {
        try {
            ProcessInfo::processInfo()->processName = Bundle::main()->object(kCFBundleNameKey);
            $content = null;
            $path = $this->request->url->path;
            if (!($endpoint = $this->endpointsByRoute[$path]) && ($entity = $this->persistentContainer->managedObjectModel->entitiesByName[$this->request->url->lastPathComponent])) {
                $datapoint = new Datapoint($this, $entity);
                if ($path === $datapoint->route()) {
                    $endpoint = $datapoint;
                }
            }
            if ($endpoint instanceof Endpoint) {
                if ($this->request->httpMethod == HTTPRequestMethod::options) {
                    $response = new HTTPURLResponse($this->request->url);
                } elseif (!$endpoint->allowedMethods()->containsElement($this->request->httpMethod)) {
                    throw new MethodNotAllowedException();
                } else {
                    if (!$this->validate($endpoint)) {
                        throw new UnauthorizedException();
                    }
                    $response = $endpoint->response();
                    $content = $endpoint->content();
                }
            } else {
                $fileManager = FileManager::default();
                // TODO: Add some limitations
                $fileURL = new URL($path, $fileManager->documentRootDirectory);
                $filePath = $fileURL->path;
                if ($fileManager->fileExists($filePath, $isDirectory) && !$isDirectory) {
                    $content = $fileManager->contents($filePath) ?? throw new InternalServerErrorException();
                    $headerFields = (function () use ($fileURL, $filePath, $content): Dictionary {
                        /** @var Dictionary<mixed> $headers */
                        $headers = new Dictionary();
                        if ($contentType = mime_content_type($filePath)) {
                            if ($content && ($encoding = mb_detect_encoding($content))) {
                                $contentType .= "; charset=$encoding";
                            }
                            $headers['Content-Type'] = $contentType;
                        }
                        $headers['Content-Length'] = filesize($filePath);
                        $headers['Content-Disposition'] = "inline; filename=$fileURL->lastPathComponent";
                        return $headers;
                    });
                    $response = match ($this->request->httpMethod) {
                        HTTPRequestMethod::options => new HTTPURLResponse($this->request->url),
                        HTTPRequestMethod::head, HTTPRequestMethod::get => new HTTPURLResponse($this->request->url, HTTPStatusCode::ok, null, $headerFields()),
                        default => throw new MethodNotAllowedException()
                    };
                } else {
                    throw new NotFoundException();
                }
            }
        } catch (Throwable $throwable) {
            if ($throwable instanceof InvalidRequestException) {
                $response = new HTTPURLResponse($this->request->url, $throwable->getCode(), null, $throwable instanceof UnauthorizedException ? new Dictionary(["WWW-Authenticate" => sprintf("%s realm=\"%s\"", AuthenticationScheme::bearer->name, $this->request->url->host ?? '')]) : null);
            } else {
                $response = new HTTPURLResponse($this->request->url, HTTPStatusCode::internalServerError);
            }
            if (self::$debugDefault) {
                error_log("Service: error: $throwable");
            }
            $content = null;
        } finally {
            /** @psalm-suppress PossiblyUndefinedVariable */
            $headers = $response->allHeaderFields; // @phpstan-ignore-line
            if ($value = $this->request->valueForHttpHeaderField("Origin")) {
                $headers['Access-Control-Allow-Origin'] = $value;
                $headers["Access-Control-Allow-Credentials"] = true;
            }
            if ($value = $this->request->valueForHttpHeaderField('Access-Control-Request-Method')) {
                $headers['Access-Control-Allow-Methods'] = $value;
            }
            if ($value = $this->request->valueForHttpHeaderField('Access-Control-Request-Headers')) {
                $headers['Access-Control-Allow-Headers'] = $value;
            }
            /** @psalm-suppress PossiblyUndefinedVariable */
            $this->send($response, $content); // @phpstan-ignore-line
        }
    }
}
