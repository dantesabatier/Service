<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLCredential;

class DigestAuthorization extends Authorization
{
    /** @var Dictionary<string> */
    public Dictionary $parameters {
        get => $this->parameters ??= $this->parameters();
    }
    public ?URLCredential $credential {
        get {
            if (!($username = $this->parameters["username"])) {
                return null;
            }
            return new URLCredential($username);
        }
    }
    public bool $isValid {
        get {
            if (!($password = $this->user?->valueForKey("password"))) {
                return false;
            }
            $parameters = $this->parameters;
            if (!($username = $parameters["username"]) || !($uri = $parameters["uri"]) || !($nonce = $parameters["nonce"]) || !($nc = $parameters["nc"]) || !($cnonce = $parameters["cnonce"]) || !($qop = $parameters["qop"]) || ($parameters["algorithm"] !== "SHA-256")) {
                return false;
            }
            $request = $this->request;
            $HA1 = hash("sha256", "$username:{$request->url->host}:$password");
            $HA2 = hash("sha256", "$request->httpMethod:$uri");
            $response = hash("sha256", "$HA1:$nonce:$nc:$cnonce:$qop:$HA2");
            return $parameters["response"] === $response;
        }
    }

    private function parameters(): Dictionary
    {
        preg_match_all("/(username|uri|nonce|nc|cnonce|qop|algorithm|response|opaque)=['\"]?([^'\",]+)/", $this->data, $matches);
        return new Dictionary(array_combine($matches[1], $matches[2]));
    }
}
