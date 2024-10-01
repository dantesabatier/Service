<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLRequest;

readonly class AuthorizationDescription
{
    public string $method;
    public string $credentials;
    /** @var Dictionary<string> */
    public Dictionary $parameters;

    public function __construct(public URLRequest $request)
    {
        $components = explode(" ", $this->request->valueForHttpHeaderField("Authorization") ?? "");
        if (count($components) !== 2) {
            $components = [AuthenticationScheme::basic->value, ""];
        }
        [$this->method, $this->credentials] = $components;
        preg_match_all("/(username|uri|nonce|nc|cnonce|qop|algorithm|response|opaque)=['\"]?([^'\",]+)/", $this->credentials, $matches);
        $this->parameters = new Dictionary(array_combine($matches[1], $matches[2]));
    }
}