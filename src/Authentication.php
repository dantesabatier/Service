<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\ComparisonPredicateModifier;
use Sabatier\Foundation\Predicates\ComparisonPredicateOptions;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\PredicateOperatorType;
use function Sabatier\Foundation\is_password;
use function Sabatier\Foundation\substring_from_index;
use function Sabatier\Foundation\substring_to_index;

class Authentication extends Responder
{
    /** @var class-string<ManagedObject> $userClass */
    public static string $userClass = "App\Model\User";
    public AuthenticationScheme $scheme = AuthenticationScheme::basic;
    public readonly ?URLCredential $credential;
    public readonly ?ManagedObject $user;
    /** @var array{string, string} */
    private readonly array $authorization;
    /** @var Dictionary<string> */
    private Dictionary $parameters;

    public function __construct()
    {
        parent::__construct();
        unset($this->credential);
        unset($this->user);
        unset($this->authorization);
        unset($this->isProtectedContentAvailable);
    }

    public function __get(string $name)
    {
        if ($name == "authorization") {
            $this->$name = (function (): array {
                if (!($authorizationValue = $this->request->valueForHttpHeaderField("Authorization")) || !($index = strpos($authorizationValue, " ")) || !($scheme = trim(substring_to_index($authorizationValue, $index))) || !($value = trim(substring_from_index($authorizationValue, $index))) || $scheme !== $this->scheme->value) {
                    return [$this->scheme->value, ""];
                }
                return [$scheme, $value];
            })();
            return $this->$name;
        } elseif ($name == "parameters") {
            $this->$name = (function (): Dictionary {
                [, $value] = $this->authorization;
                preg_match_all("/(username|uri|nonce|nc|cnonce|qop|algorithm|response|opaque)=['\"]?([^'\",]+)/", $value, $matches);
                return new Dictionary(array_combine($matches[1], $matches[2]));
            })();
            return $this->$name;
        } elseif ($name == "credential") {
            [$scheme, $value] = $this->authorization;
            $this->$name = match (AuthenticationScheme::from($scheme)) {
                AuthenticationScheme::basic => (function () use ($value): ?URLCredential {
                    $components = explode(":", base64_decode($value));
                    if (count($components) !== 2) {
                        return null;
                    }
                    [$username, $password] = $components;
                    return new URLCredential($username, $password);
                })(),
                AuthenticationScheme::digest => (function (): ?URLCredential {
                    if (!($username = $this->parameters["username"])) {
                        return null;
                    }
                    return new URLCredential($username);
                })(),
                default => null
            };
            return $this->$name;
        } elseif ($name == "user") {
            $this->$name = (function (): ?ManagedObject {
                if (!($username = $this->credential?->user ?? Application::shared()->session->valueForKey("user"))) {
                    return null;
                }
                /** @var class-string<ManagedObject> $userClass */
                $userClass = self::$userClass;
                $fetchRequest = $userClass::fetchRequest();
                $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("username"), Expression::expressionForConstantValue($username), PredicateOperatorType::like, ComparisonPredicateModifier::direct, ComparisonPredicateOptions::caseInsensitive | ComparisonPredicateOptions::diacriticInsensitive);
                try {
                    return $this->managedObjectContext->fetch($fetchRequest)->first()?->serialized($this->serialization);
                } catch (Exception) {
                    return null;
                }
            })();
            return $this->$name;
        } elseif ($name == "isProtectedContentAvailable") {
            $this->$name = $this->request->httpMethod === HTTPRequestMethod::options || Application::shared()->session->valueForKey("user") !== null || (function (): bool {
                    if (!($credential = $this->credential) || !($user = $this->user)) {
                        return false;
                    }
                    $password = $user->valueForKey("password");
                    return match ($this->scheme) {
                        AuthenticationScheme::basic => is_password($password) ? password_verify((string)$credential->password, $password) : $credential->password === $password,
                        AuthenticationScheme::digest => !is_password($password) && (function () use ($password): bool {
                                $parameters = $this->parameters;
                                if (!($username = $parameters["username"]) || !($uri = $parameters["uri"]) || !($nonce = $parameters["nonce"]) || !($nc = $parameters["nc"]) || !($cnonce = $parameters["cnonce"]) || !($qop = $parameters["qop"]) || ($parameters["algorithm"] !== "SHA-256")) {
                                    return false;
                                }
                                $realm = $this->request->url->host;
                                $HA1 = hash("sha256", "$username:$realm:$password");
                                $HA2 = hash("sha256", "{$this->request->httpMethod}:$uri");
                                $response = hash("sha256", "$HA1:$nonce:$nc:$cnonce:$qop:$HA2");
                                return $parameters["response"] === $response;
                            })(),
                        default => false
                    };
                })();
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
    #[Action]
    public function login(): void
    {
        $this->isProtectedContentAvailable ?: throw new UnauthorizedException();
        $session = Application::shared()->session;
        $session->regenerateID();
        $session->setValueForKey($this->user?->valueForKey("username"), "user");
        $this->content = json_encode($this->user, JSON_PRESERVE_ZERO_FRACTION);
        $this->contentType = "application/json";
    }

    #[Action]
    public function logout(): void
    {
        $session = Application::shared()->session;
        $session->setValueForKey(null, "user");
    }

    #[Action]
    public function token(): void
    {
        throw new UnimplementedException();
    }
}
