<?php

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Networking\URLCredential;

abstract class Authorization
{
    protected ManagedObjectContext $context {
        get => $this->manager->managedObjectContext;
    }
    protected Request $request {
        get => $this->manager->request;
    }
    protected string $data {
        get => $this->manager->authentication;
    }
    public ?Authenticatable $user {
        get {
            if (!($username = $this->credential?->user)) {
                return null;
            }
            return new IdentityManager($username, $this->context, $this->request->serialization)->currenUser;
        }
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
}
