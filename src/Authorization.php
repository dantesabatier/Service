<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\UndefinedKeyException;

/** @internal */
readonly class Authorization
{
    public ?URLCredential $credential;

    public function __construct(private RequestHeaderField $headerField)
    {
        unset($this->credential);
    }

    public function __get(string $name)
    {
        return $this->$name = match ($name) {
            "credential" => (function (): ?URLCredential {
                return match (AuthenticationScheme::tryFrom($this->headerField->name)) {
                    AuthenticationScheme::basic => (function (): ?URLCredential {
                        $components = explode(":", base64_decode($this->headerField->value));
                        if (count($components) !== 2) {
                            return null;
                        }
                        [$username, $password] = $components;
                        return new URLCredential($username, $password);
                    })(),
                    AuthenticationScheme::digest => (function (): ?URLCredential {
                        if (!($username = $this->headerField->parameters["username"])) {
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
