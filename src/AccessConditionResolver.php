<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\Predicate;
use function Sabatier\Foundation\fatal_error;

/**
 * Single point that evaluates an attribute-based access condition (`where` format string plus positional arguments) against a managed object.
 *
 * The condition may reference the resource by key path. Centralizing the translation here guarantees that a given condition means the same thing in every consumer.
 *
 * @internal
 */
final readonly class AccessConditionResolver
{
    /** @var Dictionary<mixed> The substitution context. */
    private Dictionary $variables;

    public function __construct()
    {
        $now = Date::now();
        $weekStart = strtotime("monday this week");
        $this->variables = new Dictionary([
            "\$TODAY" => $now->format("Y-m-d"),
            "\$NOW" => $now->format("Y-m-d\TH:i:s"),
            "\$WEEK_START" => new Date((float)$weekStart)->format("Y-m-d"),
            "\$WEEK_END" => new Date((float)strtotime("+6 days", (int)$weekStart))->format("Y-m-d"),
            "\$MONTH_START" => new Date((float)strtotime("first day of this month"))->format("Y-m-d"),
            "\$MONTH_END" => new Date((float)strtotime("last day of this month"))->format("Y-m-d"),
            "\$YEAR_START" => new Date((float)strtotime("first day of January this year"))->format("Y-m-d"),
            "\$YEAR_END" => new Date((float)strtotime("last day of December this year"))->format("Y-m-d"),
        ]);
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
