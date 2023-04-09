<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
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
     * @param Dictionary|null $serialization
     */
    public function __construct(private string $type, private ManagedObjectContext $context, private ?Dictionary $serialization = null)
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
            return $this->context->fetch($fetchRequest)->first()?->serialized($this->serialization);
        } catch (Exception) {
            return null;
        }
    }
}
