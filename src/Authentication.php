<?php

namespace Sabatier\Service;

use DateTimeInterface;
use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPCookie;
use Sabatier\Foundation\Networking\HTTPCookiePropertyKey;
use Sabatier\Foundation\Networking\HTTPCookieStorage;
use Sabatier\Foundation\Networking\HTTPCookieStringPolicy;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\substring_from_index;
use function Sabatier\Foundation\substring_to_index;

class Authentication extends Responder
{
    public AuthenticationScheme $scheme = AuthenticationScheme::basic;
    public readonly ?URLCredential $credential;
    public readonly ?ManagedObject $user;
    private readonly ?Authorization $authorization;
    private ?HTTPCookie $cookie = null;

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
        } elseif ($name == "isProtectedContentAvailable") {
            $this->$name = (function (): bool {
                if (HTTPCookieStorage::shared()->cookies($this->request->url)?->contains(fn(HTTPCookie $cookie): bool => $cookie->name === "ObjectID")) {
                    return true;
                }
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
                        $A1 = md5("$username:{$this->request->url->host}:$password");
                        $A2 = md5("{$this->request->httpMethod}:$uri");
                        $validResponse = md5("$A1:$nonce:$nc:$cnonce:$qop:$A2");
                        return $parameters["response"] === $validResponse;
                    })(),
                    default => false
                };
            })();
            return $this->$name;
        } else {
            return parent::__get($name);
        }
    }

    #[Action("/Login")]
    public function login(): void
    {
        $this->isProtectedContentAvailable ?: throw new UnauthorizedException();
        /** @var ManagedObject $user */
        $user = $this->user;
        $cookie = new HTTPCookie(new Dictionary([
            HTTPCookiePropertyKey::name => "objectID",
            HTTPCookiePropertyKey::value => $user->objectID->referenceObject,
            HTTPCookiePropertyKey::domain => $this->request->url->host,
            HTTPCookiePropertyKey::path => "/",
            HTTPCookiePropertyKey::version => 1,
            HTTPCookiePropertyKey::maximumAge => 60 * 60 * 8,
            HTTPCookiePropertyKey::sameSitePolicy => HTTPCookieStringPolicy::sameSiteLax,
            HTTPCookiePropertyKey::httpOnly => "TRUE"
        ]));
        $this->cookie = $cookie;
        $this->content = json_encode($user, JSON_PRESERVE_ZERO_FRACTION);
        $this->contentType = "application/json";
    }

    #[Action("/Logout")]
    public function logout(): void
    {
        if (!($cookie = HTTPCookieStorage::shared()->cookies($this->request->url)?->first(fn(HTTPCookie $cookie): bool => $cookie->name === "ObjectID"))) {
            return;
        }
        HTTPCookieStorage::shared()->deleteCookie($cookie);
    }

    public function response(): HTTPURLResponse
    {
        /** @var Dictionary $headerFields */
        $headerFields = new Dictionary();
        if ($cookie = $this->cookie) {
            $properties = new Dictionary([
                $cookie->name => $cookie->value,
                HTTPCookiePropertyKey::domain => $cookie->domain,
                HTTPCookiePropertyKey::path => $cookie->path,
                HTTPCookiePropertyKey::version => $cookie->version,
                HTTPCookiePropertyKey::sameSitePolicy => $cookie->sameSitePolicy,
                HTTPCookiePropertyKey::httpOnly => $cookie->isHTTPOnly,
                HTTPCookiePropertyKey::expires => $cookie->expiresDate?->formatted(DateTimeInterface::COOKIE),
                HTTPCookiePropertyKey::comment => $cookie->comment,
                HTTPCookiePropertyKey::commentURL => $cookie->commentURL,
                HTTPCookiePropertyKey::maximumAge => $cookie->properties[HTTPCookiePropertyKey::maximumAge],
                HTTPCookiePropertyKey::originURL => $cookie->properties[HTTPCookiePropertyKey::originURL]
            ]);
            $headerFields["Set-Cookie"] = $properties->mapValues(fn(mixed $value, string $key): string => $key . ($value === true ? "" : ("=" . human_readable_value($value))))->values->join("; ");
        }
        return new HTTPURLResponse($this->request->url, headerFields: $headerFields);
    }
}
