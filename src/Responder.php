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
use Sabatier\Foundation\URLComponents;
use function Sabatier\Foundation\string_is_equal;
use function Sabatier\Foundation\url_validate;

/**
 * An abstract interface for responding to and handling url requests.
 * @psalm-consistent-constructor
 */
abstract class Responder extends ObjectClass
{
    private array $cache = [];
    public Request $request {
        get => $this->cache[__FUNCTION__] ??= new Request();
    }
    /** @var Dictionary<mixed>|null */
    public ?Dictionary $serialization {
        get => $this->request->serialization;
    }
    /** @var Dictionary<mixed> */
    public Dictionary $headerFields;
    public ManagedObjectContext $managedObjectContext {
        get => Application::shared()->persistentContainer->viewContext;
    }
    /** @var ArrayClass<string> */
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::head, HTTPRequestMethod::options, HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::patch, HTTPRequestMethod::put, HTTPRequestMethod::delete]);
    }
    #[ExpectedValues(valuesFromClass: HTTPStatusCode::class)]
    public int $statusCode = HTTPStatusCode::ok;
    public ?string $content = null;
    public ?string $selector {
        get => $this->cache[__PROPERTY__] ??= $this->selector();
    }
    public bool $isEndpoint {
        get => $this->cache[__PROPERTY__] ??= $this->isEndpoint();
    }
    public bool $isActionable {
        get => $this->selector !== null;
    }
    /** @var bool Returns a Boolean value indicating whether this object is the first responder. */
    public bool $isFirstResponder {
        get => $this->isEndpoint || $this->isActionable;
    }
    public bool $isProtectedContentAvailable = false;
    public Response $response {
        get {
            $request = $this->request;
            $this->allowedMethods->containsElement($request->httpMethod) ?: throw new MethodNotAllowedException();
            if (match ($request->httpMethod) {
                    HTTPRequestMethod::post, HTTPRequestMethod::put, HTTPRequestMethod::patch, HTTPRequestMethod::delete => true,
                    default => false,
                } && ($selector = $this->selector)) {
                $this->perform($selector);
                if ($request->httpMethod === HTTPRequestMethod::delete) {
                    $this->statusCode = HTTPStatusCode::noContent;
                }
            }
            return new Response($this);
        }
    }

    public function __construct()
    {
        $this->headerFields = new Dictionary();
    }

    private function isEndpoint(): bool
    {
        $path = $this->request->url->path;
        $reflectionClass = new ReflectionClass($this);
        foreach ($reflectionClass->getAttributes(Endpoint::class) as $attribute) {
            $endpoint = $attribute->newInstance();
            if (string_is_equal($path, $endpoint->path ?? "/{$reflectionClass->getShortName()}", CompareOptions::caseInsensitive)) {
                return true;
            }
        }
        return false;
    }

    private function selector(): ?string
    {
        $path = $this->request->url->path;
        $reflectionClass = new ReflectionClass($this);
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $selector = $method->name;
            foreach ($method->getAttributes(Action::class) as $attribute) {
                $action = $attribute->newInstance();
                $other = $action->path ?? "/$selector";
                if (url_validate($other)) {
                    $components = new URLComponents($other);
                    $other = "$components->path$components->query";
                }
                if (string_is_equal($path, $other, CompareOptions::caseInsensitive)) {
                    return $selector;
                }
            }
        }
        return null;
    }
}
