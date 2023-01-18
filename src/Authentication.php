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
    public string $scheme = "Basic";
    public readonly URLProtectionSpace $space;
    public ?URLCredential $credential = null;

    public function __construct()
    {
        parent::__construct();
        $this->space = new URLProtectionSpace((string)$this->request->url->host, (int)$this->request->url->port, protocol: $this->request->url->scheme, realm: $this->request->url->host, authenticationMethod: ($authorizationValue = $this->request->valueForHttpHeaderField("Authorization")) ? (array_first(URLProtectionSpace::authenticationMethods, fn(string $authenticationMethod): bool => string_has_suffix($authenticationMethod, substring_to_index($authorizationValue, (int)strpos($authorizationValue, " ")), CompareOptions::caseInsensitive)) ?? URLAuthenticationMethodDefault) : URLAuthenticationMethodDefault);
    }
}
