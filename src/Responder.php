<?php

namespace Sabatier\Service;

use Exception;
use JetBrains\PhpStorm\ExpectedValues;
use ReflectionClass;
use ReflectionMethod;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLRequest;
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
    public readonly URLRequest $request;
    public readonly ?Dictionary $serialization;
    public readonly ManagedObjectContext $managedObjectContext;
    public readonly ?string $selector;
    public bool $isProtectedContentAvailable = false;
    /** @var ArrayClass<string> */
    public ArrayClass $allowedMethods;
    #[ExpectedValues(valuesFromClass: HTTPStatusCode::class)]
    public int $statusCode = HTTPStatusCode::ok;
    public ?string $content = null;
    public ?string $contentType = null;
    public ?int $contentLength = null;
    public ?string $contentDisposition = null;
    private readonly bool $isEndpoint;
    private readonly bool $isActionable;

    public function __construct()
    {
        unset($this->request);
        unset($this->serialization);
        unset($this->managedObjectContext);
        unset($this->isEndpoint);
        unset($this->isActionable);
        unset($this->selector);
        unset($this->allowedMethods);
    }

    /**
     * @throws Exception
     */
    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            "request" => Application::shared()->request,
            "serialization" => $this->serialization(),
            "managedObjectContext" => Application::shared()->persistentContainer->viewContext,
            "isEndpoint" => $this->isEndpoint(),
            "isActionable" => $this->isActionable(),
            "selector" => $this->selector(),
            "allowedMethods" => new ArrayClass([HTTPRequestMethod::head, HTTPRequestMethod::options, HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::patch, HTTPRequestMethod::put, HTTPRequestMethod::delete]),
            default => $this->valueForUndefinedKey($name)
        };
    }

    /**
     * @throws Exception
     */
    private function serialization(): ?Dictionary
    {
        if (!($string = $this->request->valueForHttpHeaderField("serialization")) || !json_validate($string) || !($array = json_decode($string, true))) {
            return null;
        }
        return Dictionary::dictionaryWithArray($array);
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

    private function isActionable(): bool
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
                    return true;
                }
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
                /** @var Action $action */
                $action = $attribute->newInstance();
                $other = $action->path ?? "/$selector";
                if (url_validate($other)) {
                    $components = new URLComponents($other);
                    $other = "$components->path$components->query";
                }
                if (string_is_equal($path, $other, CompareOptions::caseInsensitive) && $this->request->httpMethod === $action->method) {
                    return $selector;
                }
            }
        }
        return null;
    }

    /**
     * Returns a Boolean value indicating whether this object is the first responder.
     * @return bool true if the responder is the first responder; otherwise, false.
     */
    public function isFirstResponder(): bool
    {
        return $this->isEndpoint || $this->isActionable;
    }

    /**
     * @throws Exception
     */
    public function response(): HTTPURLResponse
    {
        $this->allowedMethods->containsElement($this->request->httpMethod) ?: throw new MethodNotAllowedException();
        if ($selector = $this->selector) {
            $this->perform($selector);
            if ($this->request->httpMethod === HTTPRequestMethod::delete) {
                $this->statusCode = HTTPStatusCode::noContent;
            }
        }
        return new HTTPURLResponse($this->request->url, $this->statusCode);
    }

    public function send(HTTPURLResponse $response): never
    {
        Response::from($this, $response)->send();
    }
}
