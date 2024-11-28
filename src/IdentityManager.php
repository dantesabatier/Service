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
    public ?Authenticatable $currenUser {
        get {
            $context = $this->context;
            /** @var FetchRequest<Authenticatable> $fetchRequest */
            $fetchRequest = new FetchRequest();
            $fetchRequest->entity = EntityDescription::entity("User", $context);
            $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("username"), Expression::expressionForConstantValue($this->username));
            return $context->fetch($fetchRequest)->first?->serialized($this->serialization);
        }
    }

    public function __construct(private readonly ManagedObjectContext $context, private readonly string $username, private readonly ?Dictionary $serialization = null)
    {
    }
}
