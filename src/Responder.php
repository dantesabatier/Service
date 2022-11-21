<?php

namespace Sabatier\Service;

use Exception;
use ReflectionClass;
use ReflectionMethod;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Networking\URLRequest;

/**
 * @psalm-consistent-constructor
 */
abstract class Responder extends ObjectClass
{
    public readonly URLRequest $request;
    /** @var Dictionary<mixed>|null */
    public readonly ?Dictionary $serialization;
    public readonly ManagedObjectContext $managedObjectContext;
    /** @var ArrayClass<string> */
    public ArrayClass $allowedMethods;
    public ?string $content = null;
    public ?string $contentType = null;
    public bool $isProtectedContentAvailable = false;

    public function __construct()
    {
        unset($this->request);
        unset($this->serialization);
        unset($this->managedObjectContext);
        unset($this->allowedMethods);
    }

    /**
     * @throws Exception
     */
    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            'request' => Application::shared()->request,
            'serialization' => (($string = $this->request->valueForHttpHeaderField('serialization')) && ($array = json_decode($string, true))) ? Dictionary::dictionaryWithArray($array) : null,
            'managedObjectContext' => Application::shared()->persistentContainer->viewContext,
            'allowedMethods' => new ArrayClass([HTTPRequestMethod::head, HTTPRequestMethod::options, HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::patch, HTTPRequestMethod::put, HTTPRequestMethod::delete]),
            default => $this->valueForUndefinedKey($name)
        };
    }
    
    public function isFirstResponder(): bool
    {
        $path = $this->request->url->path;
        $reflectionClass = new ReflectionClass($this);
        foreach ($reflectionClass->getAttributes(Endpoint::class) as $attribute) {
            $endpoint = $attribute->newInstance();
            if ($endpoint->path === $path) {
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
        /** @var Dictionary<mixed> $headerFields */
        $headerFields = new Dictionary();
        $headerFields["Content-Type"] = $this->contentType;
        return new HTTPURLResponse($this->request->url, HTTPStatusCode::ok, null, $headerFields);
    }
}
