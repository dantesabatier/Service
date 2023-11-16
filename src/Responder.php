<?php

namespace Sabatier\Service;

use Exception;
use ReflectionClass;
use ReflectionMethod;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Error;
use Sabatier\Foundation\ErrorRecoveryAttempting;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
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
        $attemptProceedingWithDefaultImplementation = fn(): bool => $this->allowedMethods->containsElement($this
            ->request->httpMethod) ?: throw new MethodNotAllowedException();
        $request = $this->request;
        $path = $request->url->path;
        $reflectionClass = new ReflectionClass($this);
        switch ($request->httpMethod) {
            case HTTPRequestMethod::get:
            case HTTPRequestMethod::head:
                foreach ($reflectionClass->getAttributes(Endpoint::class) as $attribute) {
                    $endpoint = $attribute->newInstance();
                    if (string_is_equal($path, $endpoint->path ?? "/{$reflectionClass->getShortName()}", CompareOptions::caseInsensitive)) {
                        return $attemptProceedingWithDefaultImplementation();
                    }
                }
                break;
            case HTTPRequestMethod::post:
            case HTTPRequestMethod::put:
            case HTTPRequestMethod::patch:
            case HTTPRequestMethod::delete:
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
                            $ok = $attemptProceedingWithDefaultImplementation();
                            if ($request->httpMethod === $action->method) {
                                $this->perform($selector);
                            }
                            return $ok;
                        }
                    }
                }
                break;
            default:
                break;
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
    public function presentError(/** @noinspection PhpUnusedParameterInspection */ Error $error): bool
    {
        return true;
    }

    /**
     * Returns a custom version of the supplied error object that’s more suitable for presentation in alert sheets and dialogs.
     *
     * When overriding this method, you can examine error and, if its localized description or recovery information is unhelpfully generic, return an error object with more specific localized text. If you do this, always use the domain and error code of the Error object to distinguish between errors whose presentation you want to customize and those you don’t. Don’t make decisions based on the localized description, recovery suggestion, or recovery options because parsing localized text is problematic.
     *
     * The default implementation of this method returns error unchanged.
     * @param Error $error The error object to customize.
     * @return Error The customized error object; if you decide not to customize the error presentation, return by sending this message to super (that is, return parent::willPresentError($error)).
     */
    public function willPresentError(Error $error): Error
    {
        return $error;
    }
}
