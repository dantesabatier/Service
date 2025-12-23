<?php

namespace Sabatier\Service;

use Exception;
use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\Set;

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
    /** @var Dictionary<string> The environment variables associated with this responder. */
    public Dictionary $environment {
        get => ProcessInfo::processInfo()->environment;
    }
    /** @var CORSPolicy The CORS policy applied to the response produced by this responder. */
    public CORSPolicy $corsPolicy {
        get {
            if (!isset($this->corsPolicy)) {
                $policy = Application::shared()->corsPolicy;
                $this->corsPolicy = new CORSPolicy($policy->allowedOrigins, $policy->allowedMethods->intersection(new Set($this->allowedMethods)), $policy->allowedHeaders->intersection(new Set($this->allowedHeaders)), $policy->allowCredentials);
            }
            return $this->corsPolicy;
        }
    }
    /** @var ManagedObjectContext The managed object context associated with this responder. */
    public ManagedObjectContext $managedObjectContext {
        get => Application::shared()->persistentContainer->viewContext;
    }
    /** @var ArrayClass<string> The allowed methods associated with this responder. */
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::head, HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::patch, HTTPRequestMethod::delete]);
    }
    /** @var ArrayClass<string> Defines the HTTP headers this responder is capable of understanding. It does not grant permission by itself; the effective allowed headers are the intersection between the responder’s declared headers and the application’s global CORS policy. */
    public ArrayClass $allowedHeaders {
        get => new ArrayClass(["Content-Type", "Authorization", "Serialization"]);
    }
    /** @var Responder|null The next responder. */
    public ?Responder $nextResponder = null;
    private ?ResponderResolution $resolution {
        /**
         * @throws Exception
         */
        get => $this->resolution ??= new ResponderResolution(static::class, $this->request->url->path);
    }
    /** @var bool Returns a Boolean value indicating whether this object is the first responder. */
    public bool $isFirstResponder {
        get => $this->resolution->matches;
    }
    /** @var string|null The selector associated with this responder. */
    public ?string $selector {
        get => $this->resolution->selector;
    }
    /** @var Set<class-string<ResponseDecorator>> The set of response decorators applied to this responder. Each decorator is applied to the response returned by the action method. */
    public Set $decorators {
        get => $this->resolution->decorators;
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
            $this->allowedMethods->containsElement($request->httpMethod) ?: throw new MethodNotAllowedException();
            $response = new Response($request->url);
            if (match ($request->httpMethod) {
                    HTTPRequestMethod::post,
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
}
