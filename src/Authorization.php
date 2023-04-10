<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\UndefinedKeyException;
use function Sabatier\Foundation\substring_from_index;
use function Sabatier\Foundation\substring_to_index;

/** @internal */
readonly class Authorization
{
    public ?URLCredential $credential;
    /** @var Dictionary<string> */
    public Dictionary $parameters;
    private string $name;
    private string $parametersView;

    public function __construct(public string $rawValue)
    {
        unset($this->name);
        unset($this->parametersView);
        unset($this->parameters);
        unset($this->credential);
    }

    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            "name" => trim(substring_to_index($this->rawValue, (int)strpos($this->rawValue, " "))),
            "parametersView" => trim(substring_from_index($this->rawValue, (int)strpos($this->rawValue, " "))),
            "parameters" => (new ArrayClass(explode(",", $this->parametersView)))->reduce(new Dictionary(), function (Dictionary $result, string $e): Dictionary {
                $components = explode("=", $e, 2);
                $result[trim($components[0])] = count($components) > 1 ? trim($components[1]) : "";
                return $result;
            }),
            "credential" => (function (): ?URLCredential {
                $scheme = AuthenticationScheme::tryFrom($this->name);
                return match ($scheme) {
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
