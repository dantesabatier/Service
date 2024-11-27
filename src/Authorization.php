<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\URLCredential;

abstract class Authorization
{
    abstract public ?URLCredential $credential {
        get;
    }
    abstract public bool $isValid {
        get;
    }
    public Request $request {
        get => Application::shared()->request;
    }
    public ?Authenticatable $user {
        get {
            if (!($username = $this->credential?->user)) {
                return null;
            }
            return new IdentityManager($username, $this->request->serialization)->user;
        }
    }

    public function __construct(public readonly string $data)
    {
    }
}
