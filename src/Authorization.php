<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\UndefinedKeyException;

readonly class Authorization
{
    public ?URLCredential $credential;
    public ?ManagedObject $user;
    public bool $isValid;

    public function __construct(public AuthenticationScheme $scheme, public string $parametersView)
    {
        unset($this->credential);
        unset($this->user);
        unset($this->isValid);
    }

    /** @suppress PHP0410 */
    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            "credential" => (function (): ?URLCredential {
                return match ($this->scheme) {
                    AuthenticationScheme::basic => (function (): ?URLCredential {
                        $components = explode(":", base64_decode($this->parametersView));
                        if (count($components) !== 2) {
                            return null;
                        }
                        [$username, $password] = $components;
                        return new URLCredential($username, $password);
                    })(),
                    AuthenticationScheme::digest => (function (): ?URLCredential {
                        /** @var Dictionary<string> $parameters */
                        $parameters = (new ArrayClass(explode(",", $this->parametersView)))->reduce(new Dictionary(), function (Dictionary $result, string $e): Dictionary {
                            $components = array_map(fn(string $e): string => trim($e), explode("=", $e));
                            if (count($components) === 2) {
                                [$name, $value] = $components;
                                $result[$name] = $value;
                            }
                            return $result;
                        });
                        if (!($username = $parameters["username"]) || !($uri = $parameters["uri"]) || !($nonce = $parameters["nonce"]) || !($nc = $parameters["nc"]) || !($cnonce = $parameters["cnonce"]) || !($qop = $parameters["qop"])) {
                            return null;
                        }
                        if (!($user = $this->fetch($username))) {
                            return null;
                        }
                        $password = $user->valueForKey("password");
                        $application = Application::shared();
                        $request = $application->request;
                        $realm = $request->url->host;
                        $A1 = md5("$username:$realm:$password");
                        $A2 = md5("$request->httpMethod:$uri");
                        $validResponse = md5("$A1:$nonce:$nc:$cnonce:$qop:$A2");
                        if ($parameters["response"] !== $validResponse) {
                            return null;
                        }
                        /** @psalm-suppress NoValue */
                        $this->user = $user;
                        return new URLCredential($username, $password);
                    })(),
                    default => null
                };
            })(),
            "user" => (function (): ?ManagedObject {
                if (!($credential = $this->credential)) {
                    return null;
                }
                return $this->fetch($credential->user);
            })(),
            "isValid" => ($credential = $this->credential) && ($user = $this->user) && password_verify($credential->password, $user->valueForKey("password")),
            default => throw new UndefinedKeyException("<Authorization is not key value coding compliant for the key \"$name\"")
        };
    }

    /**
     * @template T of ManagedObject
     * @param string $username
     * @return T|null
     */
    public function fetch(string $username)
    {
        $application = Application::shared();
        $context = $application->persistentContainer->viewContext;
        /** @var class-string<T> $managedObjectClass */
        $managedObjectClass = "App\Model\User";
        $fetchRequest = $managedObjectClass::fetchRequest();
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("username"), Expression::expressionForConstantValue($username));
        try {
            return $context->fetch($fetchRequest)->first();
        } catch (Exception) {
            return null;
        }
    }
}
