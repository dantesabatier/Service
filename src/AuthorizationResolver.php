<?php

namespace Sabatier\Service;

use Exception;
use NoDiscard;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Predicates\Predicate;

/**
 * AuthorizationResolver resolves Authorization objects by lazily determining the Authorization entity and querying permissions for a given authorizable, resource, and action.
 */
class AuthorizationResolver
{
    private InterfaceImplementorResolver $implementorResolver {
        get => $this->implementorResolver ??= new InterfaceImplementorResolver($this->managedObjectModel);
    }
    /** @var class-string<ManagedObject> */
    private string $authorizationClass {
        get => $this->authorizationClass ??= $this->implementorResolver->resolve(Authorization::class);
    }

    public function __construct(private readonly ManagedObjectModel $managedObjectModel)
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
