<?php

declare(strict_types=1);

namespace Sabatier\Service\LLM;

use Sabatier\Foundation\UUID;

/**
 * Identifies one run to an observer, and names the run that launched it when it is a subagent.
 *
 * A parent and its subagents share the client, the registry, the execution budgets and the wall
 * clock, so their events interleave in whatever an observer writes them to. The identifier tells
 * them apart and `$parentIdentifier` puts them back together: a subagent's tool calls belong to
 * the work its parent asked for, not to some unrelated activity that happened at the same time.
 *
 * `$depth` is what a reader nests by without walking the parentage — zero for the run a caller
 * started, one for a subagent it launched. Recursion is capped structurally at one level, so no
 * greater depth is reachable today; it is a number rather than a flag because the cap is a
 * decision the agent makes, not a fact about what an observer can be shown.
 *
 * The class marks its properties `readonly` one by one rather than declaring itself
 * `final readonly`, which would be shorter: a `readonly` class forbids property hooks, and
 * `$isSubagent` is one. Every stored property still carries the keyword, so the context is as
 * immutable as the shorter form would have made it.
 */
final class LLMRunContext
{
    /** @var string Distinguishes this run from every other the observer sees. */
    public readonly string $identifier;
    /** @var bool Whether this run was launched by another rather than by a caller. */
    public bool $isSubagent {
        get => $this->parentIdentifier !== null;
    }

    /**
     * @param string|null $identifier Distinguishes this run from every other the observer sees, or `null` to generate one.
     * @param string|null $parentIdentifier The run that launched this one, or `null` when a caller did.
     * @param int $depth How many runs deep this one is, counting the caller's own as zero.
     */
    public function __construct(?string $identifier = null, public readonly ?string $parentIdentifier = null, public readonly int $depth = 0)
    {
        $this->identifier = $identifier ?? new UUID()->uuidString;
    }

    /**
     * Derives the context of a run launched by this one.
     *
     * @return self A fresh identity naming this run as its parent, one level deeper.
     */
    public function child(): self
    {
        return new self(parentIdentifier: $this->identifier, depth: $this->depth + 1);
    }
}
