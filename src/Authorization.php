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

    public function __construct(public string $name, public string $challenge)
    {
        unset($this->credential);
        unset($this->parameters);
    }

    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            "parameters" => (new ArrayClass(explode(",", $this->challenge)))->reduce(new Dictionary(), function (Dictionary $result, string $e): Dictionary {
                $components = explode("=", $e, 2);
                $result[trim($components[0])] = count($components) > 1 ? trim($components[1]) : "";
                return $result;
            }),
            "credential" => match (AuthenticationScheme::from($this->name)) {
                AuthenticationScheme::basic => (function (): ?URLCredential {
                    $components = explode(":", base64_decode($this->challenge));
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
            },
            default => throw new UndefinedKeyException("<Authorization is not key value coding compliant for the key \"$name\"")
        };
    }
}
