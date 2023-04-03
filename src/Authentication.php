<?php

namespace Sabatier\Service;

use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\Networking\URLProtectionSpace;
use function Sabatier\Foundation\array_first;
use function Sabatier\Foundation\string_has_suffix;
use function Sabatier\Foundation\substring_to_index;
use const Sabatier\Foundation\Networking\URLAuthenticationMethodDefault;

abstract class Authentication extends Responder
{
    public readonly AuthenticationScheme $scheme;
    public readonly URLProtectionSpace $space;
    public ?URLCredential $credential = null;

    public function __construct()
    {
        parent::__construct();
        $this->scheme = ($authorizationValue = $this->request->valueForHttpHeaderField("Authorization")) ? AuthenticationScheme::tryFrom(substring_to_index($authorizationValue, (int)strpos($authorizationValue, " "))) ?? AuthenticationScheme::basic : AuthenticationScheme::basic;
        $this->space = new URLProtectionSpace((string)$this->request->url->host, (int)$this->request->url->port, protocol: $this->request->url->scheme, realm: $this->request->url->host, authenticationMethod: (array_first(URLProtectionSpace::authenticationMethods, fn(string $authenticationMethod): bool => string_has_suffix($authenticationMethod, $this->scheme->value, CompareOptions::caseInsensitive)) ?? URLAuthenticationMethodDefault));
    }
}
