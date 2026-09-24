<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Closure;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;

/**
 * The policy decision, subject and scopes of the request being served, read by `ToolRegistry` and `WriteAuthorizationObserver`.
 */
final class RequestSecurityContext
{
    private static ?RequestSecurityContext $current = null;
    private static int $systemWriteDepth = 0;

    /** @var FieldSecurityPolicy The field and row security policy derived from this context. */
    private(set) FieldSecurityPolicy $fieldSecurityPolicy {
        get => $this->fieldSecurityPolicy ??= new FieldLevelSecurityPolicy(new AuthorizationContext($this->user, $this->scopes, $this->isSecurityEnabled));
    }
    /** @var bool Whether the write observer checks this context's saves. */
    public bool $enforcesWrites {
        get => $this->isSecurityEnabled && !$this->isContentPublic;
    }

    /**
     * @param Authorizable|null $user The authenticated subject, or null when the request carries none.
     * @param ArrayClass<string> $scopes The subject's authorization scopes.
     * @param bool $isSecurityEnabled Whether the access policy enforces access.
     * @param ManagedObjectContext $managedObjectContext The context the request writes through.
     * @param bool $isContentPublic Whether the responder declared its content public, which exempts its writes from the write observer.
     */
    public function __construct(public readonly ?Authorizable $user, public readonly ArrayClass $scopes, public readonly bool $isSecurityEnabled, public readonly ManagedObjectContext $managedObjectContext, public readonly bool $isContentPublic = false)
    {
    }

    /**
     * Returns this context with the write observer enabled, for work that a public responder's exemption must not cover.
     */
    public function enforcingWrites(): RequestSecurityContext
    {
        return $this->isContentPublic ? new RequestSecurityContext($this->user, $this->scopes, $this->isSecurityEnabled, $this->managedObjectContext) : $this;
    }

    /**
     * Returns the context in effect, or null when none is.
     */
    public static function current(): ?RequestSecurityContext
    {
        return self::$current;
    }

    /**
     * Whether the work in progress runs inside {@see self::performAsSystem()}.
     */
    public static function isPerformingAsSystem(): bool
    {
        return self::$systemWriteDepth > 0;
    }

    /**
     * Runs a body of work under the given context, restoring the previous one afterwards.
     *
     * @template T
     * @param RequestSecurityContext $context
     * @param Closure(): T $body
     * @return T
     */
    public static function perform(RequestSecurityContext $context, Closure $body): mixed
    {
        $previous = self::$current;
        self::$current = $context;
        try {
            return $body();
        } finally {
            self::$current = $previous;
        }
    }

    /**
     * Runs a body of work whose writes are the system's bookkeeping, not the subject's, so the write observer does not check them.
     *
     * @template T
     * @param Closure(): T $body
     * @return T
     */
    public static function performAsSystem(Closure $body): mixed
    {
        self::$systemWriteDepth++;
        try {
            return $body();
        } finally {
            self::$systemWriteDepth--;
        }
    }
}
