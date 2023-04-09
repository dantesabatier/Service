<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\UndefinedKeyException;

/** @internal */
readonly class Authorization
{
    public ?URLCredential $credential;
    /** @var Dictionary<string> */
    private Dictionary $parameters;

    public function __construct(private AuthenticationScheme $scheme, private string $parametersView)
    {
        unset($this->credential);
        unset($this->parameters);
    }

    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            "parameters" => (new ArrayClass(explode(",", $this->parametersView)))->reduce(new Dictionary(), function (Dictionary $result, string $e): Dictionary {
                $components = explode("=", $e);
                if (count($components) === 2) {
                    [$name, $value] = $components;
                    $result[trim($name)] = trim($value);
                }
                return $result;
            }),
            "credential" => (function (): ?URLCredential {
                return match ($this->scheme) {
                    AuthenticationScheme::basic => (function (): ?URLCredential {
                        $components = explode(":", base64_decode($this->parametersView));
                        if (count($components) !== 2) {
                            return null;
                        }
                        [$username, $password] = $components;
                        return new URLCredential($username, $password);
                    })(),
                    AuthenticationScheme::digest => (function (): ?URLCredential {
                        if (!($username = $this->parameters["username"])) {
                            return null;
                        }
                        return new URLCredential($username);
                    })(),
                    default => null
                };
            })(),
            default => throw new UndefinedKeyException("<Authorization is not key value coding compliant for the key \"$name\"")
        };
    }

    public function perform(Authentication $authentication): bool
    {
        if (!($credential = $authentication->credential) || !($user = $authentication->user)) {
            return false;
        }
        $password = $user->valueForKey("password");
        return match ($this->scheme) {
            AuthenticationScheme::basic => password_verify((string)$credential->password, $password),
            AuthenticationScheme::digest => (function () use ($authentication, $password): bool {
                $parameters = $this->parameters;
                if (!($username = $parameters["username"]) || !($uri = $parameters["uri"]) || !($nonce = $parameters["nonce"]) || !($nc = $parameters["nc"]) || !($cnonce = $parameters["cnonce"]) || !($qop = $parameters["qop"])) {
                    return false;
                }
                $A1 = md5("$username:{$authentication->request->url->host}:$password");
                $A2 = md5("{$authentication->request->httpMethod}:$uri");
                $validResponse = md5("$A1:$nonce:$nc:$cnonce:$qop:$A2");
                return $parameters["response"] === $validResponse;
            })(),
            default => false
        };
    }
}
