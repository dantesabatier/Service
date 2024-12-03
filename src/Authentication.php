<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Networking\URLCredential;
use Sabatier\Foundation\ObjectClass;

abstract class Authentication extends ObjectClass
{
    public ?Authorizable $user {
        get => $this->user ??= $this->user();
    }
    public abstract ?URLCredential $credential {
        get;
    }
    public abstract bool $isValid {
        get;
    }

    public function __construct(public readonly AccessManager $manager)
    {
    }

    private function user(): ?Authorizable
    {
        if (!($username = $this->credential?->user)) {
            return null;
        }
        return new IdentityManager($username, $this->manager->managedObjectContext, $this->manager->isFirstResponder ? $this->manager->request->serialization : null)->currenUser;
    }
}
