<?php

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Networking\URLCredential;

abstract class Authorization
{
    abstract public ?URLCredential $credential {
        get;
    }
    abstract public bool $isValid {
        get;
    }
    public ?Authenticatable $user {
        get {
            if (!($username = $this->credential?->user)) {
                return null;
            }
            return new IdentityManager($this->context, $username, $this->request->serialization)->currenUser;
        }
    }
    private(set) AuthenticationScheme $scheme;
    private(set) string $data;

    public function __construct(public readonly Request $request, public readonly ManagedObjectContext $context)
    {
        $value = $this->request->valueForHttpHeaderField("Authorization") ?? "";
        $components = explode(" ", $value, 2);
        if (count($components) !== 2) {
            $components = [AuthenticationScheme::basic->value, ""];
        }
        [$name, $credentials] = $components;
        $this->scheme = AuthenticationScheme::tryFrom($name) ?? AuthenticationScheme::basic;
        $this->data = $credentials;
    }
}
