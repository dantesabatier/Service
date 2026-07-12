<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLCredential;

/** @internal */
final class DigestAuthentication extends Authentication
{
    #[Override]
    public AuthenticationScheme $scheme {
        get => AuthenticationScheme::digest;
    }
    /** @var Dictionary<string> */
    private(set) Dictionary $parameters {
        get {
            if (isset($this->parameters)) {
                return $this->parameters;
            }
            preg_match_all("/(username|uri|nonce|nc|cnonce|qop|algorithm|response|opaque)=['\"]?([^'\",]+)/", $this->context->authorizationHeader->value, $matches);
            return $this->parameters = new Dictionary(array_combine($matches[1], $matches[2]));
        }
    }
    private bool $isCredentialResolved = false;
    #[Override]
    private(set) ?URLCredential $credential {
        get {
            if ($this->isCredentialResolved) {
                return $this->credential;
            }
            $this->isCredentialResolved = true;
            if (!($username = $this->parameters["username"])) {
                return $this->credential = null;
            }
            return $this->credential = new URLCredential($username);
        }
    }
    #[Override]
    private(set) bool $isValid {
        get {
            if (isset($this->isValid)) {
                return $this->isValid;
            }
            if (!($password = $this->authenticatedUser?->password)) {
                return $this->isValid = false;
            }
            $parameters = $this->parameters;
            if (!($uri = $parameters["uri"]) || !($nonce = $parameters["nonce"]) || !($nc = $parameters["nc"]) || !($cnonce = $parameters["cnonce"]) || !($qop = $parameters["qop"])) {
                return $this->isValid = false;
            }
            $algo = match ($parameters["algorithm"]) {
                "SHA-512-256" => "sha512",
                "SHA-256" => "sha256",
                default => "md5"
            };
            $HA1 = $password;
            $HA2 = hash($algo, "{$this->context->httpMethod}:$uri");
            $response = hash($algo, "$HA1:$nonce:$nc:$cnonce:$qop:$HA2");
            return $this->isValid = $parameters["response"] === $response;
        }
    }

    #[Override]
    public static function isSupported(AuthenticationScheme $scheme): bool
    {
        return $scheme === AuthenticationScheme::digest;
    }
}
