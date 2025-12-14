<?php

namespace Sabatier\Service;

use Exception;
use NoDiscard;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\PredicateOperatorType;

/**
 * AuthenticationService resolves and fetches objects implementing Authorizable from a ManagedObjectModel. It lazily determines the Authorizable entity and provides a single lookup method based on username and optional serialization.
 */
class AuthenticationService
{
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
     * @param Dictionary|null $serialization Optional additional serialized data for the search.
     * @param ManagedObjectContext $context The context in which the object is managed.
     * @return Authorizable|null Returns an Authorizable object if found, or null otherwise.
     * @throws Exception
     */
    #[NoDiscard]
    public function find(string $username, ?Dictionary $serialization, ManagedObjectContext $context): ?Authorizable
    {
        /** @var FetchRequest<Authorizable> $fetchRequest */
        $fetchRequest = $this->authorizableClass::fetchRequest();
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("username"), Expression::expressionForConstantValue($username), PredicateOperatorType::like);
        $fetchRequest->includesPendingChanges = false;
        /** @var class-string<Authorizable> $authorizableClass */
        $authorizableClass = $this->authorizableClass;
        $serialization ??= $authorizableClass::defaultSerialization();
        $fetchRequest->serialization = $serialization;
        return $context->fetch($fetchRequest)->first;
    }
}
