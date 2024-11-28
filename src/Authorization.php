<?php

namespace Sabatier\Service;

use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
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
            return new IdentityManager($this->context, $username, $this->serialization)->currenUser;
        }
    }

    public function __construct(public readonly ManagedObjectContext $context, public readonly string $credentials, #[ExpectedValues(valuesFromClass: HTTPRequestMethod::class)] public string $method = HTTPRequestMethod::get, public readonly ?string $host = null, public readonly ?Dictionary $serialization = null)
    {
    }
}
