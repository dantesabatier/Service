<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use function Sabatier\Foundation\is_password;
use function Sabatier\Foundation\read_random;
use function Sabatier\Foundation\substring_from_index;
use function Sabatier\Foundation\substring_to_index;

class Authentication extends Responder
{
    /** @var class-string<ManagedObject> $userClass */
    public static string $userClass = "App\Model\User";
    public readonly Authorization $authorization;
    public readonly ?URLCredential $credential;
    public readonly ?ManagedObject $user;

    public function __construct()
    {
        parent::__construct();
        unset($this->authorization);
        unset($this->credential);
        unset($this->user);
        unset($this->isProtectedContentAvailable);
    }

    public function __get(string $name)
    {
        if ($name == "authorization") {
            $this->$name = ($authorizationValue = $this->request->valueForHttpHeaderField("Authorization")) && ($index = strpos($authorizationValue, " ")) && ($scheme = AuthenticationScheme::tryFrom(trim(substring_to_index($authorizationValue, $index)))) && ($credentials = trim(substring_from_index($authorizationValue, $index))) ? new Authorization($scheme, $credentials) : new Authorization(AuthenticationScheme::basic);
            return $this->$name;
        } elseif ($name == "credential") {
            $this->$name = match ($this->authorization->scheme) {
                AuthenticationScheme::basic => (function (): ?URLCredential {
                    $components = explode(":", base64_decode($this->authorization->credentials));
                    if (count($components) !== 2) {
                        return null;
                    }
                    [$username, $password] = $components;
                    return new URLCredential($username, $password);
                })(),
                AuthenticationScheme::digest => (function (): ?URLCredential {
                    if (!($username = $this->authorization->parameters["username"])) {
                        return null;
                    }
                    return new URLCredential($username);
                })(),
                AuthenticationScheme::bearer => (function (): ?URLCredential {
                    if (!($user = $this->userBy("token", $this->authorization->credentials))) {
                        return null;
                    }
                    return new URLCredential($user->valueForKey("username"));
                })()
            };
            return $this->$name;
        } elseif ($name == "user") {
            error_log("*******");
            $this->$name = (function (): ?ManagedObject {
                if (!($username = $this->credential?->user ?? Application::shared()->session->valueForKey("user"))) {
                    return null;
                }
                return $this->userBy("username", $username);
            })();
            return $this->$name;
        } elseif ($name == "isProtectedContentAvailable") {
            $this->$name = $this->request->httpMethod === HTTPRequestMethod::options || Application::shared()->session->valueForKey("user") !== null || (($credential = $this->credential) && ($user = $this->user) && ($password = $user->valueForKey("password")) && match ($this->authorization->scheme) {
                        AuthenticationScheme::basic => is_password($password) ? password_verify((string)$credential->password, $password) : $credential->password === $password,
                        AuthenticationScheme::digest => !is_password($password) && (function () use ($password): bool {
                                $parameters = $this->authorization->parameters;
                                if (!($username = $parameters["username"]) || !($uri = $parameters["uri"]) || !($nonce = $parameters["nonce"]) || !($nc = $parameters["nc"]) || !($cnonce = $parameters["cnonce"]) || !($qop = $parameters["qop"]) || ($parameters["algorithm"] !== "SHA-256")) {
                                    return false;
                                }
                                $HA1 = hash("sha256", "$username:{$this->request->url->host}:$password");
                                $HA2 = hash("sha256", "{$this->request->httpMethod}:$uri");
                                $response = hash("sha256", "$HA1:$nonce:$nc:$cnonce:$qop:$HA2");
                                return $parameters["response"] === $response;
                            })(),
                        AuthenticationScheme::bearer => $this->authorization->credentials === $user->valueForKey("token")
                    });
            return $this->$name;
        } elseif ($name == "allowedMethods") {
            $this->$name = new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::post]);
            return $this->$name;
        } else {
            return parent::__get($name);
        }
    }

    /**
     * @throws Exception
     */
    private function userBy(string $keyPath, mixed $constantValue): ?ManagedObject
    {
        /** @var class-string<ManagedObject> $userClass */
        $userClass = self::$userClass;
        $fetchRequest = $userClass::fetchRequest();
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath($keyPath), Expression::expressionForConstantValue($constantValue));
        return $this->managedObjectContext->fetch($fetchRequest)->first?->serialized($this->serialization);
    }

    /**
     * @throws Exception
     */
    #[Action]
    public function login(): void
    {
        $this->isProtectedContentAvailable ?: throw new UnauthorizedException();
        /** @var ManagedObject $user */
        $user = $this->user;
        $user->setValueForKey(bin2hex(read_random(32)), "token");
        $this->managedObjectContext->save();
        $session = Application::shared()->session;
        $session->regenerateID();
        $session->setValueForKey($user->valueForKey("username"), "user");
        $this->content = json_encode($user, JSON_PRESERVE_ZERO_FRACTION);
        $this->contentType = "application/json";
    }

    #[Action]
    public function logout(): void
    {
        $session = Application::shared()->session;
        $session->setValueForKey(null, "user");
    }
}
