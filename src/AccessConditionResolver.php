<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\Predicate;
use function Sabatier\Foundation\fatal_error;

/**
 * Single point that evaluates an attribute-based access condition (`where` format string plus positional arguments) against a managed object.
 *
 * The condition may reference the resource by key path and the request-scoped variables `$SUBJECT` (the authenticated managed object) and `$ENVIRONMENT` (the request environment). Variables are supplied as the substitution context at evaluation time, so that a key path traversing a variable — e.g. `$SUBJECT.department` — resolves correctly (pre-substitution does not descend into a key path's operand). Centralizing the translation here guarantees that a given condition means the same thing in every consumer.
 *
 * @internal
 */
final readonly class AccessConditionResolver
{
    /** @var Dictionary<mixed> The substitution context binding `$SUBJECT` and `$ENVIRONMENT`. */
    private Dictionary $variables;

    /**
     * @param Authorizable|null $subject The authenticated subject bound to `$SUBJECT`. Null when unauthenticated.
     * @param Dictionary<mixed> $environment The request environment bound to `$ENVIRONMENT`.
     * @param Dictionary<mixed> $request The request context (`ip`, `host`, `country`) bound to `$REQUEST`.
     */
    public function __construct(?Authorizable $subject, Dictionary $environment, Dictionary $request = new Dictionary())
    {
        /** @var Dictionary<mixed> $variables */
        $variables = new Dictionary();
        $variables["\$SUBJECT"] = $subject;
        $variables["\$ENVIRONMENT"] = $environment;
        $variables["\$REQUEST"] = $request;
        $this->variables = $variables;
    }

    /**
     * Parses a condition into a predicate.
     *
     * @param string $where The predicate format string.
     * @param list<mixed> $arguments Positional arguments for the format placeholders.
     */
    public function predicate(string $where, array $arguments = []): Predicate
    {
        return Predicate::format($where, new ArrayClass($arguments)) ?? fatal_error("Invalid access condition predicate format: \"$where\".");
    }

    /**
     * Evaluates a condition against a managed object, supplying `$SUBJECT`/`$ENVIRONMENT` as the substitution context.
     *
     * @param string $where The predicate format string.
     * @param list<mixed> $arguments Positional arguments for the format placeholders.
     * @param ManagedObject $object The managed object to evaluate the condition against.
     */
    public function evaluate(string $where, array $arguments, ManagedObject $object): bool
    {
        return $this->predicate($where, $arguments)->evaluate($object, $this->variables);
    }
}
