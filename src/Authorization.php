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
    public ManagedObjectContext $context {
        get => $this->manager->managedObjectContext;
    }
    public Request $request {
        get => $this->manager->request;
    }
    public string $data {
        get => $this->manager->data;
    }
    public ?Authenticatable $user {
        get {
            if (!($username = $this->credential?->user)) {
                return null;
            }
            return new IdentityManager($this->context, $username, $this->request->serialization)->currenUser;
        }
    }

    public function __construct(public readonly AccessManager $manager)
    {
    }
}
