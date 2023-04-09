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
    public Dictionary $parameters;

    public function __construct(public AuthenticationScheme $scheme, public string $parametersView)
    {
        unset($this->credential);
        unset($this->parameters);
    }

    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            "parameters" => (new ArrayClass(explode(",", $this->parametersView)))->reduce(new Dictionary(), function (Dictionary $result, string $e): Dictionary {
                $components = array_map(fn(string $e): string => trim($e), explode("=", $e));
                if (count($components) === 2) {
                    [$name, $value] = $components;
                    $result[$name] = $value;
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
}
