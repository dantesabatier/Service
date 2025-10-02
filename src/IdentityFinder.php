<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\AttributeType;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\PredicateOperatorType;

/** @internal */
readonly class IdentityFinder
{
    public function __construct(private ManagedObjectContext $context)
    {
    }

    /**
     * @throws Exception
     */
    public function find(string $username, ?Dictionary $serialization = null): ?Authorizable
    {
        $context = $this->context;
        /** @var FetchRequest<Authorizable> $fetchRequest */
        $fetchRequest = new FetchRequest();
        $fetchRequest->entity = EntityDescription::entity("User", $context);
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("username"), Expression::expressionForConstantValue($username), PredicateOperatorType::like);
        if ($serialization) {
            if (!$serialization->offsetExists("password")) {
                $serialization["password"] = AttributeType::string;
            }
            $fetchRequest->serialization = $serialization;
        }
        return $context->fetch($fetchRequest)->first;
    }
}
