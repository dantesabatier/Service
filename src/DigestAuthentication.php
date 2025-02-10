<?php

namespace Sabatier\Service;

use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLCredential;
use function Sabatier\Foundation\string_is_equal;

/** @internal */
class DigestAuthentication extends Authentication
{
    public AuthenticationScheme $scheme {
        get => AuthenticationScheme::digest;
    }
    /** @var Dictionary<covariant string> */
    private(set) Dictionary $parameters {
        get {
            if (!isset($this->parameters)) {
                preg_match_all("/(username|uri|nonce|nc|cnonce|qop|algorithm|response|opaque)=['\"]?([^'\",]+)/", $this->request->authParameter->value, $matches);
                $this->parameters = new Dictionary(array_combine($matches[1], $matches[2]));
            }
            return $this->parameters;
        }
    }
    private(set) ?URLCredential $credential {
        get => $this->credential ??= ($username = $this->parameters["username"]) ? new URLCredential($username) : null;
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
            $HA1 = hash($algo, "$username:{$this->request->url->host}:$password");
            $HA2 = hash($algo, "{$this->request->httpMethod}:$uri");
            $response = hash($algo, "$HA1:$nonce:$nc:$cnonce:$qop:$HA2");
            return $parameters["response"] === $response;
        }
    }

    public static function canInit(Request $request): bool
    {
        return string_is_equal($request->authParameter->name, AuthenticationScheme::digest->value, CompareOptions::caseInsensitive);
    }
}
