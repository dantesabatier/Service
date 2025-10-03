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
 * An abstract class for responding to and handling url requests.
 * @psalm-consistent-constructor
 * @phpstan-consistent-constructor
 */
abstract class Responder extends ObjectClass
{
    private const string isFirstResponderKey = "isFirstResponder";
    private const string selectorKey = "selector";
    /** @var Request The request associated with this responder. */
    public Request $request {
        get => self::$staticAssociatedValues[self::class][__PROPERTY__] ??= new Request();
    }
    /** @var Dictionary<string> The header fields associated with this responder. */
    public Dictionary $headerFields {
        get => $this->headerFields ??= new Dictionary();
    }
    /** @var ManagedObjectContext The managed object context associated with this responder. */
    public ManagedObjectContext $managedObjectContext {
        get => Application::shared()->persistentContainer->viewContext;
    }
    /** @var ArrayClass<HTTPRequestMethod> The allowed methods associated with this responder. */
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::head, HTTPRequestMethod::options, HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::patch, HTTPRequestMethod::put, HTTPRequestMethod::delete]);
    }
    /** @var int The status code associated with this responder. */
    #[ExpectedValues(valuesFromClass: HTTPStatusCode::class)]
    public int $statusCode = HTTPStatusCode::ok;
    /** @var string|null The content associated with this responder. */
    public ?string $content = null;
    /** @var string|null The selector associated with this responder. */
    public ?string $selector {
        get => $this->associatedValues[__PROPERTY__] ?? null;
    }
    /** @var Responder|null The next responder. */
    public ?Responder $nextResponder = null;
    /** @var bool Returns a Boolean value indicating whether this object is the first responder. */
    public bool $isFirstResponder {
        get {
            if (!isset($this->associatedValues[__PROPERTY__])) {
                $this->initializeResponder();
            }
            return $this->associatedValues[__PROPERTY__];
        }
    }
    /** @var bool Checks if the protected content is available by determining if the request is authorized. */
    public bool $isProtectedContentAvailable = false;
    /** @var Response The response associated with this responder. */
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

    private function initializeResponder(): void
    {
        $path = $this->request->url->path;
        $reflectionClass = new ReflectionClass($this);
        foreach ($reflectionClass->getAttributes(Endpoint::class) as $attribute) {
            $endpoint = $attribute->newInstance();
            if (string_is_equal($path, $endpoint->path ?? "/{$reflectionClass->getShortName()}", CompareOptions::caseInsensitive)) {
                $this->associatedValues[self::isFirstResponderKey] = true;
            }
        }
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
                    $this->associatedValues[self::isFirstResponderKey] = true;
                    $this->associatedValues[self::selectorKey] = $selector;
                    break;
                }
            }
        }
        $this->associatedValues[self::isFirstResponderKey] ??= false;
        $this->associatedValues[self::selectorKey] ??= null;
    }
}
