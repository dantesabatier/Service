<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLCredential;

abstract class Authentication
{
    public ?Dictionary $serialization {
        get {
            if (!($this->manager->isFirstResponder)) {
                return null;
            }
            return $this->manager->request->serialization;
        }
    }
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
        return new IdentityManager($username, $this->manager->managedObjectContext, $this->serialization)->currenUser;
    }
}
