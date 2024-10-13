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
use Sabatier\Foundation\Error;
use Sabatier\Foundation\ErrorRecoveryAttempting;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPStatusCode;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLRequest;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\URLComponents;
use function Sabatier\Foundation\human_readable_value;
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
    public readonly bool $isEndpoint;
    public readonly bool $isActionable;
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
            "isActionable" => $this->selector !== null,
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

    private function isReading(): bool
    {
        return match ($this->request->httpMethod) {
            HTTPRequestMethod::options, HTTPRequestMethod::head, HTTPRequestMethod::get => true,
            default => false,
        };
    }

    private function isWriting(): bool
    {
        return match ($this->request->httpMethod) {
            HTTPRequestMethod::post, HTTPRequestMethod::put, HTTPRequestMethod::patch, HTTPRequestMethod::delete => true,
            default => false,
        };
    }

    private function isEndpoint(): bool
    {
        if (!$this->isReading()) {
            return false;
        }
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
        if (!$this->isWriting()) {
            return null;
        }
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

    private function isResponseEmpty(HTTPURLResponse $response): bool
    {
        return match ($response->statusCode) {
            HTTPStatusCode::created, HTTPStatusCode::noContent, HTTPStatusCode::resetContent, HTTPStatusCode::notModified => true,
            default => $response instanceof BatchResponse ? $response->isEmpty : empty($this->content)
        };
    }

    private function responseHeaderFields(HTTPURLResponse $response): Dictionary
    {
        $headerFields = $response->allHeaderFields;
        $headerFields["Content-Type"] = $this->contentType;
        $headerFields["Content-Length"] = $this->contentLength;
        $headerFields["Content-Disposition"] = $this->contentDisposition;
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
        if ($this->isResponseEmpty($response)) {
            $headerFields->removeAll(fn(mixed $e, string $k): bool => match ($k) {
                "Content-Type", "Content-Length", "Content-Disposition" => true,
                default => false
            });
        }
        return $headerFields;
    }

    private function willSend(HTTPURLResponse $response): void
    {
        if (headers_sent()) {
            die();
        }
        foreach (["Expires", "Cache-Control", "Pragma"] as $header) {
            header_remove($header);
        }
        header(sprintf("%s %s %s", $response->httpVersion, $response->statusCode, HTTPURLResponse::localizedString($response->statusCode)));
    }

    private function sendBatchResponse(BatchResponse $response): never
    {
        flush();
        header_register_callback(function () use ($response): void {
            $headers = $this->responseHeaderFields($response);
            foreach ($headers as $key => $value) {
                header(sprintf("%s: %s", $key, human_readable_value($value)));
                flush();
            }
        });
        if ($this->isResponseEmpty($response)) {
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

    private function sendResponse(HTTPURLResponse $response): never
    {
        $headers = $this->responseHeaderFields($response);
        foreach ($headers as $key => $value) {
            header(sprintf("%s: %s", $key, human_readable_value($value)));
        }
        if ($this->isResponseEmpty($response)) {
            die();
        }
        ob_start();
        /** @noinspection SpellCheckingInspection */
        ob_start("ob_gzhandler");
        echo $this->content;
        ob_end_flush();
        header("Content-Length: " . ob_get_length());
        ob_end_flush();
        die();
    }

    public function send(HTTPURLResponse $response): never
    {
        $this->willSend($response);
        if ($response instanceof BatchResponse) {
            $this->sendBatchResponse($response);
        }
        $this->sendResponse($response);
    }

    /**
     * Presents an error alert to the user as an application-modal dialog.
     *
     * The alert displays information found in the Error object $error; this information can include error description, recovery suggestion, failure reason, and button titles (all localized). The method returns true if error recovery succeeded and false otherwise. For error recovery to be attempted, a recovery-attempter object (that is, an object conforming to the {@see ErrorRecoveryAttempting} informal protocol) must be associated with error.
     *
     * The default implementation of this method sends {@see willPresentError()} to self. By doing this, Responder gives subclasses an opportunity to customize error presentation. It then forwards the message, passing any customized error object, to the next responder; if there is no next responder, it passes the error object to App, which displays a document-modal error alert. When the user dismisses the alert, any recovery attempter associated with the error object is given a chance to recover from the error. See the class description for the precise route up the responder chain (plus document and controller objects) this message might travel.
     *
     * It is not recommended that you attempt to override this method. If you wish to customize the error presentation, override {@see willPresentError()} instead.
     * @param Error $error An object containing information about an error.
     * @return bool
     */
    public function presentError(Error $error): bool
    {
        if (Application::shared() === $this) {
            return true;
        }
        return Application::shared()->presentError($this->willPresentError($error));
    }

    /**
     * Returns a custom version of the supplied error object that's more suitable for presentation in alert sheets and dialogs.
     *
     * When overriding this method, you can examine error and, if its localized description or recovery information is unhelpfully generic, return an error object with more specific localized text. If you do this, always use the domain and error code of the Error object to distinguish between errors whose presentation you want to customize and those you don't. Don't make decisions based on the localized description, recovery suggestion, or recovery options because parsing localized text is problematic.
     *
     * The default implementation of this method returns error unchanged.
     * @param Error $error The error object to customize.
     * @return Error The customized error object; if you decide not to customize the error presentation, return by sending this message to parent (that is, return parent::willPresentError($error)).
     */
    public function willPresentError(Error $error): Error
    {
        return $error;
    }
}
