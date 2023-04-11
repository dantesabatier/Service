<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use function Sabatier\Foundation\substring_from_index;
use function Sabatier\Foundation\substring_to_index;

class Authentication extends Responder
{
    public AuthenticationScheme $scheme = AuthenticationScheme::basic;
    public readonly ?URLCredential $credential;
    public readonly ?ManagedObject $user;
    public readonly string $challenge;
    private readonly ?Authorization $authorization;

    public function __construct()
    {
        parent::__construct();
        unset($this->challenge);
        unset($this->credential);
        unset($this->user);
        unset($this->authorization);
        unset($this->isProtectedContentAvailable);
    }

    public function __get(string $name)
    {
        if ($name == "authorization") {
            $this->$name = (function (): ?Authorization {
                if (!($authorizationValue = $this->request->valueForHttpHeaderField("Authorization")) || !($index = strpos($authorizationValue, " ")) || !($scheme = trim(substring_to_index($authorizationValue, $index))) || !($rawValue = trim(substring_from_index($authorizationValue, $index))) || $scheme !== $this->scheme->value) {
                    return null;
                }
                return new Authorization($scheme, $rawValue);
            })();
            return $this->$name;
        } elseif ($name == "credential") {
            $this->$name = $this->authorization?->credential;
            return $this->$name;
        } elseif ($name == "user") {
            $this->$name = (function (): ?ManagedObject {
                if (!($username = $this->credential?->user)) {
                    return null;
                }
                /** @var class-string<ManagedObject> $type */
                $type = "App\Model\User";
                $fetchRequest = $type::fetchRequest();
                $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("username"), Expression::expressionForConstantValue($username));
                try {
                    return $this->managedObjectContext->fetch($fetchRequest)->first()?->serialized($this->serialization);
                } catch (Exception) {
                    return null;
                }
            })();
            return $this->$name;
        } elseif ($name == "challenge") {
            $this->$name = "{$this->scheme->value} realm=\"{$this->request->url->host}\"" . match ($this->scheme) {
                    AuthenticationScheme::digest => sprintf(", uri=\"%s\", qop=\"auth\", nonce=\"%s\", opaque=\"%s\" algorithm=\"SHA-256\"", $this->request->url->absoluteString, uniqid(), base64_encode((string)$this->request->url->host)),
                    default => ""
                };
            return $this->$name;
        } elseif ($name == "isProtectedContentAvailable") {
            $this->$name = $this->request->httpMethod === HTTPRequestMethod::options || (isset($_SESSION["user"]) || (function (): bool {
                        if (!($credential = $this->credential) || !($user = $this->user)) {
                            return false;
                        }
                        $password = $user->valueForKey("password");
                        return match ($this->scheme) {
                            AuthenticationScheme::basic => password_verify((string)$credential->password, $password),
                            AuthenticationScheme::digest => (function () use ($password): bool {
                                /** @var Dictionary<string> $parameters */
                                $parameters = $this->authorization?->parameters;
                                if (!($username = $parameters["username"]) || !($uri = $parameters["uri"]) || !($nonce = $parameters["nonce"]) || !($nc = $parameters["nc"]) || !($cnonce = $parameters["cnonce"]) || !($qop = $parameters["qop"])) {
                                    return false;
                                }
                                if (!($algo = match ($parameters["algorithm"] ?? "SHA-256") {
                                    "SHA-512-256" => "sha512",
                                    "MD5" => "md5",
                                    "SHA-256" => "sha256",
                                    default => null
                                })) {
                                    return false;
                                }
                                $A1 = hash($algo, "$username:{$this->request->url->host}:$password");
                                $A2 = hash($algo, "{$this->request->httpMethod}:$uri");
                                $response = hash($algo, "$A1:$nonce:$nc:$cnonce:$qop:$A2");
                                return $parameters["response"] === $response;
                            })(),
                            default => false
                        };
                    })());
            return $this->$name;
        } elseif ($name == "allowedMethods") {
            $this->$name = new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::post]);
            return $this->$name;
        } else {
            return parent::__get($name);
        }
    }

    #[Action("/Login")]
    public function login(): void
    {
        if (!$this->isProtectedContentAvailable || !($user = $this->user)) {
            throw new UnauthorizedException();
        }
        $_SESSION["user"] = $user->objectID->referenceObject;
        $this->content = json_encode($user, JSON_PRESERVE_ZERO_FRACTION);
        $this->contentType = "application/json";
    }

    #[Action("/Logout")]
    public function logout(): void
    {
        unset($_SESSION["user"]);
    }
}
