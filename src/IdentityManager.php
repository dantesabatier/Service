<?php

namespace Sabatier\Service;

use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\PredicateOperatorType;

/** @internal */
class IdentityManager
{
    public ?Authorizable $user {
        get {
            $context = $this->context;
            /** @var FetchRequest<Authorizable> $fetchRequest */
            $fetchRequest = new FetchRequest();
            $fetchRequest->entity = EntityDescription::entity("User", $context);
            $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("username"), Expression::expressionForConstantValue($this->username), PredicateOperatorType::like);
            if ($serialization = $this->serialization) {
                if (!$serialization->offsetExists("password")) {
                    $serialization["password"] = AttributeType::string;
                }
                $fetchRequest->serialization = $serialization;
            }
            return $context->fetch($fetchRequest)->first;
        }
    }

    public function __construct(private readonly string $username, private readonly ManagedObjectContext $context, private readonly ?Dictionary $serialization = null)
    {
    }
}
