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
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\ObjectClass;
use function Sabatier\Foundation\human_readable_value;

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
        $request = $this->request;
        $path = $request->url->path;
        $reflectionClass = new ReflectionClass($this);
        foreach ($reflectionClass->getAttributes(Endpoint::class) as $attribute) {
            $endpoint = $attribute->newInstance();
            if ($endpoint->path == $path && $request->httpMethod == HTTPRequestMethod::get) {
                return true;
            }
        }
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(Action::class) as $attribute) {
                $action = $attribute->newInstance();
                if ($action->path == $path) {
                    if ($request->httpMethod == $action->method) {
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

    public function send(HTTPURLResponse $response, ?string $content): void
    {
        $isEmpty = match ($response->statusCode) {
            HTTPStatusCode::created, HTTPStatusCode::noContent, HTTPStatusCode::resetContent, HTTPStatusCode::notModified => true,
            default => $response instanceof BatchResponse ? $response->isEmpty : empty($content)
        };
        if ($isEmpty) {
            foreach (["Content-Type", "Content-Length"] as $key) {
                $response->allHeaderFields->removeValueForKey($key);
            }
        }
        header(sprintf("%s %s %s", $response->httpVersion, $response->statusCode, HTTPURLResponse::localizedString($response->statusCode)));
        if ($response instanceof BatchResponse) {
            flush();
            header_register_callback(function () use ($response) {
                foreach ($response->allHeaderFields as $key => $value) {
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
        foreach ($response->allHeaderFields as $key => $value) {
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
    }
}
