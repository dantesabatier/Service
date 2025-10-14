<?php

namespace Sabatier\Service;

use Override;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;

/** @internal */
final class DefaultAuthenticationService implements AuthenticationService
{
    #[Override]
    public function find(ManagedObjectContext $context, string $username, ?Dictionary $serialization = null): ?Authorizable
    {
        return null;
    }
}
