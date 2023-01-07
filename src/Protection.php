<?php

namespace Sabatier\Service;

use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\Networking\URLProtectionSpace;
use const Sabatier\Foundation\Networking\URLAuthenticationMethodDefault;
use function Sabatier\Foundation\array_first;
use function Sabatier\Foundation\string_has_suffix;
use function Sabatier\Foundation\substring_to_index;

abstract class Protection extends Responder
{
    public string $scheme = "Basic";
    public readonly URLProtectionSpace $space;
    public ?URLCredential $credential = null;

    public function __construct()
    {
        parent::__construct();
        $url = $this->request->url;
        $host = $url->host ?? throw new BadRequestException();
        if ($this->request->httpMethod != HTTPRequestMethod::options) {
            $authorization = $this->request->valueForHttpHeaderField("Authorization") ?? throw new UnauthorizedException();
            $scheme = substring_to_index($authorization, (int)strpos($authorization, " "));
            $authenticationMethod = array_first(URLProtectionSpace::authenticationMethods, fn(string $authenticationMethod): bool => string_has_suffix($authenticationMethod, $scheme, CompareOptions::caseInsensitive)) ?? URLAuthenticationMethodDefault;
            $this->space = new URLProtectionSpace($host, $url->port, realm: $host, authenticationMethod: $authenticationMethod);
        } else {
            $this->space = new URLProtectionSpace($host);
        }
    }
}
