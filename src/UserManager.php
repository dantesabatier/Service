<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\Expression;

/**
 * @template T of ManagedObject
 * @internal
 */
readonly class UserManager
{
    /**
     * @param class-string<T> $type
     * @param ManagedObjectContext $context
     */
    public function __construct(private string $type, private ManagedObjectContext $context)
    {
    }

    /**
     * @param string $username
     * @return T|null
     */
    public function fetch(string $username)
    {
        $type = $this->type;
        $fetchRequest = $type::fetchRequest();
        $fetchRequest->predicate = new ComparisonPredicate(Expression::expressionForKeyPath("username"), Expression::expressionForConstantValue($username));
        try {
            return $this->context->fetch($fetchRequest)->first();
        } catch (Exception) {
            return null;
        }
    }
}
