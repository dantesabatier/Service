<?php

namespace Sabatier\Service;

use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;

class IdentityManager
{
    public ?Authorizable $currenUser {
        get {
            $context = $this->context;
            /** @var FetchRequest<Authenticatable> $fetchRequest */
            $fetchRequest = new FetchRequest();
            $fetchRequest->entity = EntityDescription::entity("User", $context);
            $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("username"), Expression::expressionForConstantValue($this->username));
            if ($serialization = $this->serialization) {
                $fetchRequest->serialization = $serialization;
            }
            return $context->fetch($fetchRequest)->first;
        }
    }

    public function __construct(private readonly string $username, private readonly ManagedObjectContext $context, private readonly ?Dictionary $serialization = null)
    {
    }
}
