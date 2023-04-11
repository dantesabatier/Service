<?php

namespace Sabatier\Service;

use Exception;
use ReflectionClass;
use ReflectionMethod;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\ObjectClass;

/**
 * An abstract interface for responding to and handling url requests.
 * @psalm-consistent-constructor
 */
abstract class Responder extends ObjectClass
{
    public readonly URLRequest $request;
    /** @var Dictionary|null */
    public readonly ?Dictionary $serialization;
    public readonly ManagedObjectContext $managedObjectContext;
    /** @var ArrayClass<string> */
    public ArrayClass $allowedMethods;
    public ?string $content = null;
    public ?string $contentType = null;
    public ?int $contentLength = null;
    public ?string $contentDisposition = null;
    public bool $isProtectedContentAvailable = false;

    public function __construct()
    {
        unset($this->request);
        unset($this->serialization);
        unset($this->managedObjectContext);
        unset($this->allowedMethods);
    }

    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            "request" => Application::shared()->request,
            "serialization" => (($string = $this->request->valueForHttpHeaderField("serialization")) && ($array = json_decode($string, true))) ? Dictionary::dictionaryWithArray($array) : null,
            "managedObjectContext" => Application::shared()->persistentContainer->viewContext,
            "allowedMethods" => new ArrayClass([HTTPRequestMethod::head, HTTPRequestMethod::options, HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::patch, HTTPRequestMethod::put, HTTPRequestMethod::delete]),
            default => $this->valueForUndefinedKey($name)
        };
    }

    /**
     * Returns a Boolean value indicating whether this object is the first responder.
     * @return bool true if the responder is the first responder; otherwise, false.
     */
    public function isFirstResponder(): bool
    {
        $path = $this->request->url->path;
        $reflectionClass = new ReflectionClass($this);
        foreach ($reflectionClass->getAttributes(Endpoint::class) as $attribute) {
            $endpoint = $attribute->newInstance();
            if ($endpoint->path === $path && $this->request->httpMethod === HTTPRequestMethod::get) {
                return true;
            }
        }
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(Action::class) as $attribute) {
                $action = $attribute->newInstance();
                if ($action->path === $path) {
                    if ($this->request->httpMethod === $action->method) {
                        $this->perform($method->name);
                    }
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @throws Exception
     */
    public function response(): HTTPURLResponse
    {
        return new HTTPURLResponse($this->request->url);
    }
}
