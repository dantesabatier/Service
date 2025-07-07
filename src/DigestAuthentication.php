<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\URLCredential;

/** @internal */
class DigestAuthentication extends Authentication
{
    public AuthenticationScheme $scheme {
        get => AuthenticationScheme::digest;
    }
    private(set) ?URLCredential $credential {
        get => $this->credential ??= ($username = $this->request->authenticationToken->parameters["username"]) ? new URLCredential($username) : null;
    }
    public bool $isValid {
        get {
            if (!($password = $this->user?->password)) {
                return false;
            }
            $parameters = $this->request->authenticationToken->parameters;
            if (!($username = $parameters["username"]) || !($uri = $parameters["uri"]) || !($nonce = $parameters["nonce"]) || !($nc = $parameters["nc"]) || !($cnonce = $parameters["cnonce"]) || !($qop = $parameters["qop"])) {
                return false;
            }
            $algo = match ($parameters["algorithm"]) {
                "SHA-512-256" => "sha512",
                "SHA-256" => "sha256",
                default => "md5"
            };
            $HA1 = hash($algo, "$username:{$this->request->url->host}:$password");
            $HA2 = hash($algo, "{$this->request->httpMethod}:$uri");
            $response = hash($algo, "$HA1:$nonce:$nc:$cnonce:$qop:$HA2");
            return $parameters["response"] === $response;
        }
    }

    public static function isSupported(AuthenticationScheme $scheme): bool
    {
        return $scheme === AuthenticationScheme::digest;
    }
}
