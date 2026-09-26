<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObject;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Nil;
use Sabatier\Foundation\Predicates\Predicate;
use function Sabatier\Foundation\fatal_error;

/**
 * Single point that evaluates an attribute-based access condition (`where` format string plus positional arguments) against a managed object.
 *
 * The condition may reference the resource by key path. Centralizing the translation here guarantees that a given condition means the same thing in every consumer.
 *
 * @internal
 */
final class AccessConditionResolver
{
    /** @var Dictionary<string|Nil> The substitution variables — temporal (`$TODAY`, `$NOW`, `$WEEK_START`, …) and `$REMOTE_ADDRESS`, the TCP peer address read from `REMOTE_ADDR` as {@see Request::$remoteAddress} does, null outside an HTTP request — bound as the substitution context at evaluation time. */
    public Dictionary $variables {
        get {
            if (isset($this->variables)) {
                return $this->variables;
            }
            $now = Date::now();
            $weekStart = strtotime("monday this week");
            return $this->variables = new Dictionary([
                "\$TODAY" => $now->format("Y-m-d"),
                "\$NOW" => $now->format("Y-m-d\TH:i:s"),
                "\$WEEK_START" => Date::dateWithTimeIntervalSince1970((float)$weekStart)->format("Y-m-d"),
                "\$WEEK_END" => Date::dateWithTimeIntervalSince1970((float)strtotime("+6 days", (int)$weekStart))->format("Y-m-d"),
                "\$MONTH_START" => Date::dateWithTimeIntervalSince1970((float)strtotime("first day of this month"))->format("Y-m-d"),
                "\$MONTH_END" => Date::dateWithTimeIntervalSince1970((float)strtotime("last day of this month"))->format("Y-m-d"),
                "\$YEAR_START" => Date::dateWithTimeIntervalSince1970((float)strtotime("first day of January this year"))->format("Y-m-d"),
                "\$YEAR_END" => Date::dateWithTimeIntervalSince1970((float)strtotime("last day of December this year"))->format("Y-m-d"),
                "\$REMOTE_ADDRESS" => $_SERVER["REMOTE_ADDR"] ?? Nil::nil(),
            ]);
        }
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
     * Evaluates a condition against a managed object, supplying the temporal substitution variables as the substitution context.
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
