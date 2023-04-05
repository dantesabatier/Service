<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\CompareOptions;
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
    private readonly ?AuthenticationScheme $authenticationScheme;

    public function __construct()
    {
        parent::__construct();
        $host = $this->request->url->host;
        $authorizationView = (string)$this->request->valueForHttpHeaderField("Authorization");
        $index = (int)strpos($authorizationView, " ");
        $credentials = trim(substring_from_index($authorizationView, $index));
        $this->authenticationScheme = AuthenticationScheme::tryFrom(substring_to_index($authorizationView, $index));
        $this->space = new URLProtectionSpace((string)$host, (int)$this->request->url->port, protocol: $this->request->url->scheme, realm: $host, authenticationMethod: $this->authenticationMethod() ?? URLAuthenticationMethodDefault);
        $this->credential = match ($this->authenticationScheme) {
            AuthenticationScheme::basic => (function () use ($credentials): ?URLCredential {
                $components = explode(":", base64_decode($credentials));
                if (count($components) !== 2) {
                    return null;
                }
                [$user, $password] = $components;
                return new URLCredential($user, $password);
            })(),
            AuthenticationScheme::bearer => (function () use ($host, $credentials): ?URLCredential {
                if (count(explode(".", $credentials)) !== 3) {
                    return null;
                }
                $environment = ProcessInfo::processInfo()->environment;
                $key = $environment["JWT_KEY"] ?? "";
                $decoder = new JWTDecoder($key, $host);
                try {
                    if (!($username = $decoder->decode($credentials)["username"])) {
                        return null;
                    }
                    return new URLCredential($username);
                } catch (Exception) {
                    return null;
                }
            })(),
            default => null
        };
        $this->isProtectedContentAvailable = $this->credential !== null;
    }

    private function authenticationMethod(): ?string
    {
        if (!($authenticationScheme = $this->authenticationScheme)) {
            return null;
        }
        return array_first(URLProtectionSpace::authenticationMethods, fn(string $authenticationMethod): bool => string_has_suffix($authenticationMethod, $authenticationScheme->value, CompareOptions::caseInsensitive));
    }
}
