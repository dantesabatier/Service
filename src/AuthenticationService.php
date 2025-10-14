<?php

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;

interface AuthenticationService
{
    /**
     * Finds an Authorizable object based on the provided context, username, and optional serialization data.
     *
     * @param ManagedObjectContext $context The context in which the object is managed.
     * @param string $username The username to identify the desired object.
     * @param Dictionary|null $serialization Optional additional serialized data for the search.
     * @return Authorizable|null Returns an Authorizable object if found, or null otherwise.
     */
    public function find(ManagedObjectContext $context, string $username, ?Dictionary $serialization = null): ?Authorizable;
}
