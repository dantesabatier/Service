<?php

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Networking\URLCredential;

abstract class Authentication
{
    private(set) ManagedObjectContext $context;
    private(set) Request $request;
    private(set) string $data;
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

    public function __construct(AccessManager $manager)
    {
        $this->data = $manager->data;
        $this->context = $manager->managedObjectContext;
        $this->request = $manager->request;
    }
}
