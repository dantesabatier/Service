<?php

namespace Sabatier\Service;

use JetBrains\PhpStorm\ExpectedValues;
use ReflectionClass;
use ReflectionMethod;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URLComponents;
use Sabatier\Foundation\UserDefaults;
use function Sabatier\Foundation\string_is_equal;
use function Sabatier\Foundation\url_validate;

/**
 * An abstract class for responding to and handling url requests.
 * @psalm-consistent-constructor
 * @phpstan-consistent-constructor
 */
abstract class Responder extends ObjectClass
{
    /** @var Request The request associated with this responder. */
    public Request $request {
        get => self::$staticAssociatedValues[self::class][__PROPERTY__] ??= new Request();
    }
    public Session $session {
        get => self::$staticAssociatedValues[self::class][__PROPERTY__] ??= new Session();
    }
    /**
     * @var CORSPolicy The CORS policy applied to the response produced by this responder.
     *
     * The policy defines which origins, HTTP methods, and request headers are
     * permitted to access the response. It is evaluated by internal response
     * decorators during response construction and emission.
     *
     * If no policy is provided or the policy does not allow the request origin,
     * no CORS headers are added to the response.
     *
     * The default policy is resolved from the application configuration and may
     * be overridden by subclasses to provide responder-specific behavior.
     * */
    public CORSPolicy $corsPolicy {
        get => self::$staticAssociatedValues[self::class][__PROPERTY__] ??= new CORSPolicy(UserDefaults::standard()->dictionary(CORSAllowedOriginsPreferenceKey) ?? new Dictionary(), new Set(UserDefaults::standard()->array(CORSAllowedMethodsPreferenceKey) ?? $this->allowedMethods), new Set(UserDefaults::standard()->array(CORSAllowedHeadersPreferenceKey) ?? ["Content-Type", "Authorization", "Serialization"]), UserDefaults::standard()->bool(CORSAllowCredentialsPreferenceKey));
    }
    /** @var ManagedObjectContext The managed object context associated with this responder. */
    public ManagedObjectContext $managedObjectContext {
        get => Application::shared()->persistentContainer->viewContext;
    }
    /** @var ArrayClass<string> The allowed methods associated with this responder. */
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::head, HTTPRequestMethod::options, HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::patch, HTTPRequestMethod::put, HTTPRequestMethod::delete]);
    }
    /** @var Responder|null The next responder. */
    public ?Responder $nextResponder = null;
    /** @var bool Returns a Boolean value indicating whether this object is the first responder. */
    public bool $isFirstResponder {
        get {
            if (!isset($this->isFirstResponder)) {
                [$this->isFirstResponder, $this->selector, $this->decorators] = $this->initializeResponder();
            }
            return $this->isFirstResponder;
        }
    }
    /** @var string|null The selector associated with this responder. */
    public ?string $selector = null;
    /** @var Set<class-string<ResponseDecorator>> The set of response decorators applied to this responder. Each decorator is applied to the response returned by the action method. */
    public Set $decorators {
        get => $this->decorators ??= new Set();
    }
    /** @var mixed The data produced or returned by the responder's action method. This value is used as the body of the response or as input to response decorators. */
    public mixed $data = null;
    /** @var int The HTTP status code to be returned in the response. */
    #[ExpectedValues(valuesFromClass: HTTPStatusCode::class)]
    public int $statusCode = HTTPStatusCode::ok;
    public AuthenticationService $authenticationService {
        get => Application::shared()->authenticationService;
    }
    public AuthorizationService $authorizationService {
        get => Application::shared()->authorizationService;
    }
    /** @var bool Checks if the protected content is available by determining if the request is authorized. */
    public bool $isProtectedContentAvailable = false;
    /** @var Response The response associated with this responder. */
    public Response $response {
        get {
            $request = $this->request;
            $response = new Response($request->url);
            $this->allowedMethods->containsElement($request->httpMethod) ?: throw new MethodNotAllowedException();
            if (match ($request->httpMethod) {
                    HTTPRequestMethod::post,
                    HTTPRequestMethod::put,
                    HTTPRequestMethod::patch,
                    HTTPRequestMethod::delete => true,
                    default => false,
                } && ($selector = $this->selector)) {
                $session = $this->session;
                $session->start();
                $this->perform($selector);
                $session->commit();
                $response = new Response($request->url, $this->statusCode, body: $this->data);
            }
            foreach ($this->decorators as $decorator) {
                $response = new $decorator($response)->response;
            }
            return new CORSResponseDecorator($response, $request, $this->corsPolicy)->response;
        }
    }

    private function initializeResponder(): array
    {
        $isFirstResponder = false;
        $selector = null;
        /** @var Set<class-string<ResponseDecorator>> $decorators */
        $decorators = new Set();
        $path = $this->request->url->path;
        $reflectionClass = new ReflectionClass($this);
        foreach ($reflectionClass->getAttributes(Endpoint::class) as $attribute) {
            $endpoint = $attribute->newInstance();
            $other = $endpoint->path ?? "/{$reflectionClass->getShortName()}";
            if (!str_starts_with($other, "/")) {
                $other = "/$other";
            }
            if (string_is_equal($path, $other, CompareOptions::caseInsensitive)) {
                $isFirstResponder = true;
                $decorators->appendContentsOf($endpoint->decorators);
            }
        }
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $methodName = $method->name;
            foreach ($method->getAttributes(Action::class) as $attribute) {
                $action = $attribute->newInstance();
                $other = $action->path ?? "/$methodName";
                if (url_validate($other)) {
                    $components = new URLComponents($other);
                    $other = "$components->path$components->query";
                }
                if (string_is_equal($path, $other, CompareOptions::caseInsensitive)) {
                    $isFirstResponder = true;
                    $selector = $methodName;
                    $decorators->appendContentsOf($action->decorators);
                    break 2;
                }
            }
        }
        $decorators->append(ResponseHeaderSanitizerDecorator::class);
        return [$isFirstResponder, $selector, $decorators];
    }
}
