<?php

declare(strict_types=1);

namespace Sabatier\Service;

use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\Networking\HTTPCookieStringPolicy;

/**
 * Parameters for setting HTTP cookies.
 * @psalm-type CookieParametersValues array{lifetime: int, path: string, domain: string, secure: bool, httponly: bool, samesite: string}
 */
final readonly class CookieParameters
{
    /** @var CookieParametersValues $allValues */
    public array $allValues;

    /**
     * Initializes a new instance of the class with the specified properties.
     *
     * @param string $domain The domain for which the cookie is valid.
     * @param string $path The path on the server in which the cookie will be available. The default value is "/".
     * @param int $lifetime The lifetime of the cookie in seconds. Default is 0.
     * @param bool $isSecure Whether the cookie is marked as secure. Default is true.
     * @param bool $isHTTPOnly Whether the cookie is marked as HTTP-only. Default is true.
     * @param string $sameSitePolicy The SameSite policy for the cookie. Must be a value defined in HTTPCookieStringPolicy.
     */
    public function __construct(public string $domain, public string $path = "/", public int $lifetime = 0, public bool $isSecure = true, public bool $isHTTPOnly = true, #[ExpectedValues(valuesFromClass: HTTPCookieStringPolicy::class)] public string $sameSitePolicy = HTTPCookieStringPolicy::sameSiteLax)
    {
        $this->allValues = ["lifetime" => $this->lifetime, "path" => $this->path, "domain" => $this->domain, "secure" => $this->isSecure, "httponly" => $this->isHTTPOnly, "samesite" => $this->sameSitePolicy];
    }
}
