<?php

namespace Sabatier\Service;

use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\Networking\HTTPCookieStringPolicy;
use Sabatier\Foundation\URLComponents;
use function Sabatier\Foundation\request_url;

readonly class CookieParameters
{
    public array $allValues;

    public function __construct(public string $domain, public string $path = "/", public int $lifetime = 0, public bool $isSecure = true, public bool $isHTTPOnly = true, #[ExpectedValues(valuesFromClass: HTTPCookieStringPolicy::class)] public string $sameSitePolicy = HTTPCookieStringPolicy::sameSiteLax)
    {
        /** @noinspection SpellCheckingInspection */
        $this->allValues = ["lifetime" => $this->lifetime, "path" => $this->path, "domain" => $this->domain, "secure" => $this->isSecure, "httponly" => $this->isHTTPOnly, "samesite" => $this->sameSitePolicy];
    }

    public static function default(): CookieParameters
    {
        return new CookieParameters((string)(new URLComponents(request_url()))->host);
    }
}
