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
            if (!($password = $this->user?->password)) {
                return false;
            }
            $parameters = $this->parameters;
            if (!($username = $parameters["username"]) || !($uri = $parameters["uri"]) || !($nonce = $parameters["nonce"]) || !($nc = $parameters["nc"]) || !($cnonce = $parameters["cnonce"]) || !($qop = $parameters["qop"])) {
                return false;
            }
            $algo = match ($parameters["algorithm"]) {
                "SHA-512-256" => "sha512",
                "SHA-256" => "sha256",
                default => "md5"
            };
            $request = Application::shared()->request;
            $HA1 = hash($algo, "$username:{$request->url->host}:$password");
            $HA2 = hash($algo, "$request->httpMethod:$uri");
            $response = hash($algo, "$HA1:$nonce:$nc:$cnonce:$qop:$HA2");
            return $parameters["response"] === $response;
        }
    }
}
