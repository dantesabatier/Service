<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\ComparisonPredicateModifier;
use Sabatier\Foundation\Predicates\CompoundPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\PredicateOperatorType;
use Sabatier\Foundation\Set;

final  class AuthorizationScopeBuilder
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
     * @return ArrayClass<non-empty-string>
     * @throws Exception
     */
    public function build(Authorizable $subject, ManagedObjectContext $context): ArrayClass
    {
        /** @var FetchRequest<Authorization> $fetchRequest */
        $fetchRequest = $this->authorizationClass::fetchRequest();
        $fetchRequest->predicate = CompoundPredicate::andPredicateWithSubpredicates(new ArrayClass([new ComparisonPredicate(Expression::expressionForKeyPath("roles.name"), Expression::expressionForConstantValue($subject->roles->map(fn(AuthorizableRole $role): string => $role->name)), PredicateOperatorType::in, ComparisonPredicateModifier::any)]));
        return new ArrayClass(new Set($context->fetch($fetchRequest)->map(fn(Authorization $authorization): string => "$authorization->name:{$authorization->type->name}")));
    }
}
