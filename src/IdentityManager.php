<?php

namespace Sabatier\Service;

use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;

class IdentityManager
{
    public ?Authenticatable $user {
        get {
            $context = Application::shared()->persistentContainer->viewContext;
            /** @var FetchRequest<Authenticatable> $fetchRequest */
            $fetchRequest = new FetchRequest();
            $fetchRequest->entity = EntityDescription::entity("User", $context);
            $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("username"), Expression::expressionForConstantValue($this->username));
            return $context->fetch($fetchRequest)->first?->serialized($this->serialization);
        }
    }

    public function __construct(public readonly string $username, public readonly ?Dictionary $serialization = null)
    {
    }
}
