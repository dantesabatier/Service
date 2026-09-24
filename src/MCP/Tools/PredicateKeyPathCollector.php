<?php

/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace Sabatier\Service\MCP\Tools;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Predicates\Expression;
use Sabatier\Foundation\Predicates\ExpressionType;
use Sabatier\Foundation\Predicates\Predicate;
use Sabatier\Foundation\Predicates\PredicateOperator;
use Sabatier\Foundation\Predicates\PredicateVisitor;
use Sabatier\Foundation\Predicates\PredicateVisitorFlags;
use Sabatier\Foundation\Predicates\SubqueryExpression;
use Sabatier\Foundation\Set;

/** @internal */
final class PredicateKeyPathCollector implements PredicateVisitor
{
    /** @var Set<string> The root-relative key paths found. */
    private(set) Set $keyPaths;
    /** @var Dictionary<string> The collection key path each subquery variable ranges over, by variable. */
    private Dictionary $variables;

    public function __construct()
    {
        $this->keyPaths = new Set();
        $this->variables = new Dictionary();
    }

    /**
     * @return Set<string>
     */
    public static function keyPaths(Predicate $predicate): Set
    {
        $collector = new PredicateKeyPathCollector();
        $predicate->accept($collector, PredicateVisitorFlags::common);
        return $collector->keyPaths;
    }

    #[Override]
    public function visitPredicate(Predicate $predicate): void
    {
    }

    #[Override]
    public function visitPredicateOperator(PredicateOperator $operator): void
    {
    }

    #[Override]
    public function visitPredicateExpression(Expression $expression): void
    {
        if ($expression instanceof SubqueryExpression) {
            if (($collection = $this->rootRelative($expression->collectionExpression->predicateFormat)) !== null) {
                $this->variables[$expression->variableExpression->predicateFormat] = $collection;
                $this->keyPaths->insert($collection);
            }
            return;
        }
        if ($expression->expressionType !== ExpressionType::keyPath) {
            return;
        }
        if (($keyPath = $this->rootRelative($expression->predicateFormat)) !== null) {
            $this->keyPaths->insert($keyPath);
        }
    }

    private function rootRelative(string $keyPath): ?string
    {
        if (str_contains($keyPath, "(")) {
            return null;
        }
        $parts = new ArrayClass(explode(".", $keyPath));
        /** @var string $head */
        $head = $parts->first;
        if (!str_starts_with($head, "$")) {
            return $keyPath;
        }
        $collection = $this->variables[$head];
        if ($collection === null) {
            return null;
        }
        $rest = $parts->dropFirst(1);
        return $rest->isEmpty ? $collection : "$collection.{$rest->join(".")}";
    }
}
