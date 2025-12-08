<?php

namespace Sabatier\Service;

use Exception;
use NoDiscard;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\PredicateOperatorType;

readonly class AuthenticationService
{
    /**
     * Initializes a new instance of the AuthenticationService class.
     *
     * @param EntityDescription $authorizableEntity The class name of the managed object representing an Authorizable.
     */
    public function __construct(private EntityDescription $authorizableEntity)
    {
    }

    /**
     * Finds an Authorizable object based on the provided context, username, and optional serialization data.
     *
     * @param string $username The username to identify the desired object.
     * @param Dictionary|null $serialization Optional additional serialized data for the search.
     * @param ManagedObjectContext $context The context in which the object is managed.
     * @return Authorizable|null Returns an Authorizable object if found, or null otherwise.
     * @throws Exception
     */
    #[NoDiscard]
    public function find(string $username, ?Dictionary $serialization, ManagedObjectContext $context): ?Authorizable
    {
        /** @var FetchRequest<Authorizable> $fetchRequest */
        $fetchRequest = new FetchRequest();
        $fetchRequest->entity = $this->authorizableEntity;
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("username"), Expression::expressionForConstantValue($username), PredicateOperatorType::like);
        $fetchRequest->includesPendingChanges = false;
        /** @var class-string<Authorizable> $authorizableClass */
        $authorizableClass = $this->authorizableEntity->managedObjectClassName;
        $serialization ??= $authorizableClass::defaultSerialization();
        $fetchRequest->serialization = $serialization;
        return $context->fetch($fetchRequest)->first;
    }
}
