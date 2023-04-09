<?php

namespace Sabatier\Service;

use DateTimeInterface;
use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPCookie;
use Sabatier\Foundation\Networking\HTTPCookiePropertyKey;
use Sabatier\Foundation\Networking\HTTPCookieStorage;
use Sabatier\Foundation\Networking\HTTPCookieStringPolicy;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use Sabatier\Foundation\Networking\URLProtectionSpace;
use function Sabatier\Foundation\array_first;
use function Sabatier\Foundation\human_readable_value;
use function Sabatier\Foundation\string_has_suffix;
use function Sabatier\Foundation\substring_from_index;
use function Sabatier\Foundation\substring_to_index;
use const Sabatier\Foundation\Networking\URLAuthenticationMethodDefault;

class Authentication extends Responder
{
    public AuthenticationScheme $scheme = AuthenticationScheme::basic;
    public readonly ?Authorization $authorization;
    public readonly string $method;
    private ?HTTPCookie $cookie = null;

    public function __construct()
    {
        parent::__construct();
        unset($this->authorization);
        unset($this->method);
        unset($this->isProtectedContentAvailable);
    }

    public function __get(string $name)
    {
        if ($name == "authorization") {
            $this->$name = ($authorizationValue = $this->request->valueForHttpHeaderField("Authorization")) && ($index = strpos($authorizationValue, " ")) && (($scheme = trim(substring_to_index($authorizationValue, $index)))) && ($parametersView = trim(substring_from_index($authorizationValue, $index))) && ($scheme = AuthenticationScheme::tryFrom($scheme)) && $scheme === $this->scheme ? new Authorization($scheme, $parametersView) : null;
            return $this->$name;
        } elseif ($name == "method") {
            $this->$name = array_first(URLProtectionSpace::authenticationMethods, fn(string $authenticationMethod): bool => string_has_suffix($authenticationMethod, $this->scheme->value, CompareOptions::caseInsensitive)) ?? URLAuthenticationMethodDefault;
            return $this->$name;
        } elseif ($name == "isProtectedContentAvailable") {
            $this->$name = $this->authorization?->isValid ?? false;
            return $this->$name;
        } else {
            return parent::__get($name);
        }
    }

    #[Action("/Login")]
    public function login(): void
    {
        $this->authorization?->isValid ?: throw new UnauthorizedException();
        /** @var ManagedObject $user */
        $user = $this->authorization?->user;
        $this->content = json_encode($user, JSON_PRESERVE_ZERO_FRACTION);
        $this->contentType = "application/json";
        $this->cookie = new HTTPCookie(new Dictionary([
            HTTPCookiePropertyKey::name => "objectID",
            HTTPCookiePropertyKey::value => $user->objectID->referenceObject,
            HTTPCookiePropertyKey::domain => $this->request->url->host,
            HTTPCookiePropertyKey::path => $this->request->url->path,
            HTTPCookiePropertyKey::version => 1,
            HTTPCookiePropertyKey::maximumAge => 60 * 60 * 8,
            HTTPCookiePropertyKey::sameSitePolicy => HTTPCookieStringPolicy::sameSiteLax,
            HTTPCookiePropertyKey::httpOnly => "TRUE"
        ]));
    }

    #[Action("/Logout")]
    public function logout(): void
    {
        if (!($cookie = $this->request->valueForHttpHeaderField("Cookie"))) {
            return;
        }
        $components = explode("=", $cookie);
        if (count($components) !== 2) {
            return;
        }
        [$name, $value] = $components;
        if (!($cookie = HTTPCookieStorage::shared()->cookies($this->request->url)?->first(fn(HTTPCookie $cookie): bool => $cookie->name === $name && $cookie->value === $value))) {
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
            $headerFields["Set-Cookie"] = $properties->mapValues(fn(mixed $value, string $key): string => sprintf("%s=%s", $key, human_readable_value($value)))->values->join("; ");
        }
        return new HTTPURLResponse($this->request->url, headerFields: $headerFields);
    }
}
