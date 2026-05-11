<?php

namespace Sabatier\Service;

use Exception;
use NoDiscard;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\ComparisonPredicateModifier;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\PredicateOperatorType;

/**
 * AuthorizationResolver resolves Authorization objects by lazily determining the Authorization entity and querying permissions for a given authorizable, resource, and action.
 */
final class AuthorizationResolver
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
     * Resolves all authorizations for a given authorizable across every resource and action.
     *
     * Used by the persistent cache to store the full permission set so that subsequent
     * requests for any resource or action hit the cache instead of the database.
     *
     * @param Authorizable $authorizable The authorizable entity (e.g., user or role).
     * @param ManagedObjectContext $context The managed object context for data access.
     * @return ArrayClass<Authorization> The collection of all resolved authorizations.
     * @throws Exception
     */
    #[NoDiscard]
    public function resolve(Authorizable $authorizable, ManagedObjectContext $context): ArrayClass
    {
        /** @var FetchRequest<Authorization> $fetchRequest */
        $fetchRequest = $this->authorizationClass::fetchRequest();
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("roles.name"), Expression::expressionForConstantValue($authorizable->roles->map(fn(AuthorizableRole $role): string => $role->name)), PredicateOperatorType::in, ComparisonPredicateModifier::any);
        return $context->fetch($fetchRequest);
    }
}
