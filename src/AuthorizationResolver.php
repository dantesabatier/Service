<?php

namespace Sabatier\Service;

use Exception;
use NoDiscard;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Predicates\Predicate;

readonly class AuthorizationResolver
{
    /**
     * Initializes a new instance of the AuthorizationResolver class.
     *
     * @param class-string<ManagedObject> $authorizationClass The class name of the managed object representing authorizations.
     */
    public function __construct(private string $authorizationClass)
    {
    }

    /**
     * Resolves authorizations for a given authorizable, resource, and action.
     *
     * @param Authorizable $authorizable The authorizable entity (e.g., user or role).
     * @param string $resource The resource for which authorization is being checked.
     * @param AuthorizationType $action The action to be authorized.
     * @param ManagedObjectContext $context The managed object context for data access.
     * @return ArrayClass<Authorization> The collection of resolved authorizations.
     * @throws Exception
     */
    #[NoDiscard]
    public function resolve(Authorizable $authorizable, string $resource, AuthorizationType $action, ManagedObjectContext $context): ArrayClass
    {
        /** @var FetchRequest<Authorization> $fetchRequest */
        $fetchRequest = $this->authorizationClass::fetchRequest();
        $fetchRequest->predicate = Predicate::format("%K = %@ AND %K = %@ AND ANY %K IN %@", new ArrayClass(["name", $resource, "type", $action, "roles.name", $authorizable->roles->map(fn(AuthorizableRole $role) => $role->name)]));
        return $context->fetch($fetchRequest);
    }
}
