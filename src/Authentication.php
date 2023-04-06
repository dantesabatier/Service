<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\Networking\URLProtectionSpace;
use Sabatier\Foundation\ProcessInfo;
use function Sabatier\Foundation\array_first;
use function Sabatier\Foundation\string_has_suffix;
use function Sabatier\Foundation\substring_from_index;
use function Sabatier\Foundation\substring_to_index;
use const Sabatier\Foundation\Networking\URLAuthenticationMethodDefault;

abstract class Authentication extends Responder
{
    public AuthenticationScheme $scheme = AuthenticationScheme::basic;
    public readonly ?URLCredential $credential;
    public readonly URLProtectionSpace $space;

    public function __construct()
    {
        parent::__construct();
        $authorizationValue = (string)$this->request->valueForHttpHeaderField("Authorization");
        $index = (int)strpos($authorizationValue, " ");
        $scheme = trim(substring_to_index($authorizationValue, $index));
        $credentials = trim(substring_from_index($authorizationValue, $index));
        $authenticationScheme = AuthenticationScheme::tryFrom($scheme);
        $authenticationMethod = (function () use ($authenticationScheme): ?string {
            if (!$authenticationScheme) {
                return null;
            }
            return array_first(URLProtectionSpace::authenticationMethods, fn(string $authenticationMethod): bool => string_has_suffix($authenticationMethod, $authenticationScheme->value, CompareOptions::caseInsensitive));
        })() ?? URLAuthenticationMethodDefault;
        $credential = match ($authenticationScheme) {
            AuthenticationScheme::basic => (function () use ($credentials): ?URLCredential {
                $components = explode(":", base64_decode($credentials));
                if (count($components) !== 2) {
                    return null;
                }
                [$user, $password] = $components;
                return new URLCredential($user, $password);
            })(),
            AuthenticationScheme::bearer => (function () use ($credentials): ?URLCredential {
                $environment = ProcessInfo::processInfo()->environment;
                $key = $environment["JWT_KEY"] ?? "";
                /** @psalm-suppress PossiblyNullArgument */
                $decoder = new JWTDecoder($key, $this->request->url->host);
                if (!($decoded = $decoder->decode($credentials)) || !($username = $decoded["username"])) {
                    return null;
                }
                return new URLCredential($username);
            })(),
            default => null
        };
        $this->space = new URLProtectionSpace((string)$this->request->url->host, (int)$this->request->url->port, null, $this->request->url->scheme, $this->request->url->host, $authenticationMethod);
        $this->credential = $credential;
        $this->isProtectedContentAvailable = $this->credential !== null;
        $this->allowedMethods = new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::options]);
    }
}
