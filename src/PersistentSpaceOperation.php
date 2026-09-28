<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Sabatier\CoreData\ManagedObjectContext;

/**
 * An operation on the persistent space that both HTTP and MCP go through, so the security rules it
 * applies live in one place and run in one order: {@see self::perform()} runs {@see self::willPerform()},
 * {@see self::performCore()} and {@see self::didPerform()}, and nothing reaches the data any other way.
 *
 * @internal
 * @template TResult
 */
abstract class PersistentSpaceOperation
{
    public function __construct(protected readonly ManagedObjectContext $context, protected readonly FieldSecurityPolicy $fieldSecurityPolicy)
    {
    }

    /**
     * Performs the operation.
     *
     * @return TResult
     * @throws Exception
     */
    final public function perform(): mixed
    {
        $this->willPerform();
        $result = $this->performCore();
        $this->didPerform();
        return $result;
    }

    /**
     * @return TResult
     * @throws Exception
     */
    abstract protected function performCore(): mixed;

    /**
     * @throws Exception
     */
    protected function willPerform(): void
    {
    }

    /**
     * @throws Exception
     */
    protected function didPerform(): void
    {
    }
}
