<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLCredential;

class DigestAuthorization extends Authorization
{
    /** @var Dictionary<string> */
    public Dictionary $parameters {
        get {
            preg_match_all("/(username|uri|nonce|nc|cnonce|qop|algorithm|response|opaque)=['\"]?([^'\",]+)/", $this->data, $matches);
            return new Dictionary(array_combine($matches[1], $matches[2]));
        }
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
            if (!($username = $parameters["username"]) || !($uri = $parameters["uri"]) || !($nonce = $parameters["nonce"]) || !($nc = $parameters["nc"]) || !($cnonce = $parameters["cnonce"]) || !($qop = $parameters["qop"]) || !($algorithm = hash_algos()[$parameters["algorithm"]])) {
                return false;
            }
            $request = Application::shared()->request;
            $HA1 = hash($algorithm, "$username:{$request->url->host}:$password");
            $HA2 = hash($algorithm, "$request->httpMethod:$uri");
            $response = hash($algorithm, "$HA1:$nonce:$nc:$cnonce:$qop:$HA2");
            return $parameters["response"] === $response;
        }
    }
}
