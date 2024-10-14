<?php

namespace Sabatier\Service;

use Exception;
use JetBrains\PhpStorm\Deprecated;
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
    /** @var ArrayClass<string> */
    public ArrayClass $allowedMethods;
    public bool $isProtectedContentAvailable = false;
    #[ExpectedValues(valuesFromClass: HTTPStatusCode::class)]
    public int $statusCode = HTTPStatusCode::ok;
    public ?string $content = null;
    #[Deprecated]
    public ?string $contentType = null;
    #[Deprecated]
    public ?int $contentLength = null;
    #[Deprecated]
    public ?string $contentDisposition = null;
    /** @var Dictionary<mixed> */
    public Dictionary $headerFields;
    public readonly ?string $selector;
    public readonly bool $isEndpoint;
    public readonly bool $isActionable;

    public function __construct()
    {
        unset($this->request);
        unset($this->serialization);
        unset($this->managedObjectContext);
        unset($this->allowedMethods);
        unset($this->headerFields);
        unset($this->selector);
        unset($this->isEndpoint);
        unset($this->isActionable);
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
            "allowedMethods" => new ArrayClass([HTTPRequestMethod::head, HTTPRequestMethod::options, HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::patch, HTTPRequestMethod::put, HTTPRequestMethod::delete]),
            "headerFields" => $this->headerFields(),
            "selector" => $this->selector(),
            "isEndpoint" => $this->isEndpoint(),
            "isActionable" => $this->selector !== null,
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

    private function headerFields(): Dictionary
    {
        /** @var Dictionary<mixed> $headerFields */
        $headerFields = new Dictionary();
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
        return $headerFields;
    }

    /**
     * Returns a Boolean value indicating whether this object is the first responder.
     * @return bool true if the responder is the first responder; otherwise, false.
     */
    public function isFirstResponder(): bool
    {
        return $this->allowedMethods->containsElement($this->request->httpMethod) && ($this->isEndpoint || $this->isActionable);
    }

    /**
     * @throws Exception
     */
    public function response(): HTTPURLResponse
    {
        if (match ($this->request->httpMethod) {
                HTTPRequestMethod::post, HTTPRequestMethod::put, HTTPRequestMethod::patch, HTTPRequestMethod::delete => true,
                default => false,
            } && ($selector = $this->selector)) {
            $this->perform($selector);
            if ($this->request->httpMethod === HTTPRequestMethod::delete) {
                $this->statusCode = HTTPStatusCode::noContent;
            }
        }
        return new HTTPURLResponse($this->request->url, $this->statusCode, headerFields: $this->headerFields);
    }
}
