<?php

namespace Sabatier\Service;

use Exception;
use NoDiscard;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\PredicateOperatorType;

/**
 * Resolves and fetches `Authorizable` entities from the persistent store for authentication.
 *
 * On first use, `AuthenticationService` scans the managed object model to find the entity class
 * that implements `Authorizable`. This resolution is lazy and cached for the lifetime of the
 * service instance, so the model is only inspected once per request.
 *
 * `find()` fetches the matching user by username, merging the caller-supplied serialization with
 * the minimum set of attributes required for authentication and token validation (username,
 * password, enabled flag, refresh token version, and role names). This ensures authentication
 * never issues unnecessary SQL joins beyond what is needed for credential verification.
 *
 * @see Authorizable
 * @see AuthenticationScheme
 */
final class AuthenticationService
{
    /** @var Dictionary<mixed>|null */
    private static ?Dictionary $authenticationRequirements = null;
    private InterfaceImplementorResolver $implementorResolver {
        get => $this->implementorResolver ??= new InterfaceImplementorResolver($this->managedObjectModel);
    }
    /** @var class-string<ManagedObject> */
    private string $authorizableClass {
        get => $this->authorizableClass ??= $this->implementorResolver->resolve(Authorizable::class);
    }

    public function __construct(private readonly ManagedObjectModel $managedObjectModel)
    {
    }

    /**
     * Finds an Authorizable object based on the provided context, username, and optional serialization data.
     *
     * @param string $username The username to identify the desired object.
     * @param Dictionary<mixed>|null $serialization Optional additional serialized data for the search.
     * @param ManagedObjectContext $context The context in which the object is managed.
     * @return Authorizable|null Returns an Authorizable object if found, or null otherwise.
     * @throws Exception
     */
    #[NoDiscard]
    public function find(string $username, ?Dictionary $serialization, ManagedObjectContext $context): ?Authorizable
    {
        /** @var FetchRequest<Authorizable> $fetchRequest */
        $fetchRequest = $this->authorizableClass::fetchRequest();
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath(AuthenticationUsernameKey), Expression::expressionForConstantValue($username), PredicateOperatorType::like);
        $fetchRequest->includesPendingChanges = false;
        /** @var class-string<Authorizable> $authorizableClass */
        $authorizableClass = $this->authorizableClass;
        self::$authenticationRequirements ??= Dictionary::dictionaryWithArray([
            AuthenticationUsernameKey => AttributeType::string,
            AuthenticationPasswordKey => AttributeType::string,
            AuthenticationIsEnabledKey => AttributeType::boolean,
            AuthenticationRefreshTokenVersionKey => AttributeType::integer32,
            AuthenticationRolesKey => [
                AuthenticationRoleNameKey => AttributeType::string
            ]
        ]);
        $serialization ??= $authorizableClass::defaultRepresentation();
        $fetchRequest->serialization = $serialization->merging(self::$authenticationRequirements);
        return $context->fetch($fetchRequest)->first;
    }
}
