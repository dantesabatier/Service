<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\UserDefaults;

/** @internal */
class BearerAuthentication extends Authentication
{
    public AuthenticationScheme $scheme {
        get => AuthenticationScheme::bearer;
    }
    private(set) ?URLCredential $credential {
        get {
            if (!isset($this->credential)) {
                if ($key = UserDefaults::standard()->string(JWTPrivateKeyPreferenceKey)) {
                    $algorithm = JSONWebTokenSigningAlgorithm::tryFrom((string)UserDefaults::standard()->string(JWTSignatureAlgorithmPreferenceKey)) ?? JSONWebTokenSigningAlgorithm::hs256;
                    if ($JSONWebTokenDecoderStrategyClass = JSONWebTokenCoderStrategyFactory::shared()->getStrategyClass(JSONWebTokenCoderStrategyFactory::shared()->decoderStrategies, $algorithm)) {
                        $data = $this->request->authParameter->value;
                        $strategy = new $JSONWebTokenDecoderStrategyClass($key, $this->request->url->host);
                        $decoder = new JSONWebTokenDecoder($strategy);
                        $token = $decoder->decode($data);
                        if ($username = $token->payload->username) {
                            $this->credential = new URLCredential($username);
                        }
                    }
                }
                $this->credential ??= null;
            }
            return $this->credential;
        }
    }
    public bool $isValid {
        get => $this->credential instanceof URLCredential;
    }

    public static function isSupported(AuthenticationScheme $scheme): bool
    {
        return $scheme === AuthenticationScheme::bearer;
    }
}
