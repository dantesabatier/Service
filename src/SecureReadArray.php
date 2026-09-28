<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\CoreData\ManagedObject;
use Sabatier\CoreData\Materializable;
use Sabatier\Foundation\ArrayClass;
use function Sabatier\Foundation\invalid_mutation;

/**
 * The result of a fetch, each object filtered to the fields the subject may read when it is accessed and never before.
 *
 * Filtering on access keeps a batched fetch batched: the rows are read as they are iterated, the way
 * `BatchFaultingArray` faults them in, so streaming a large result holds one batch at a time. Object
 * IDs and dictionary rows are returned as they come.
 *
 * @internal
 * @extends ArrayClass<mixed>
 */
final class SecureReadArray extends ArrayClass implements Materializable
{
    /** @var int<0, max> */
    #[Override]
    public int $count {
        get => $this->source->count;
    }
    /** @var list<mixed> */
    #[Override]
    public array $array {
        get => iterator_to_array($this);
    }

    /**
     * @param ArrayClass<mixed> $source The result of the fetch.
     * @param FieldSecurityPolicy $fieldSecurityPolicy The policy filtering each object.
     */
    public function __construct(private readonly ArrayClass $source, private readonly FieldSecurityPolicy $fieldSecurityPolicy)
    {
        parent::__construct();
    }

    #[Override]
    public function __clone()
    {
        $this->source = clone $this->source;
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function materialize(mixed $element): mixed
    {
        return $element instanceof ManagedObject ? $this->fieldSecurityPolicy->applySecureRead($element, $element->jsonSerialize()) : $element;
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function current(): mixed
    {
        return $this->materialize($this->source->current());
    }

    #[Override]
    public function key(): int
    {
        return $this->source->key();
    }

    #[Override]
    public function next(): void
    {
        $this->source->next();
    }

    #[Override]
    public function valid(): bool
    {
        return $this->source->valid();
    }

    #[Override]
    public function rewind(): void
    {
        $this->source->rewind();
    }

    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        return $this->source->offsetExists($offset);
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->materialize($this->source->offsetGet($offset));
    }

    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        invalid_mutation();
    }

    #[Override]
    public function append(mixed $element): void
    {
        invalid_mutation();
    }

    #[Override]
    public function insertAt(mixed $element, int $at): void
    {
        invalid_mutation();
    }

    #[Override]
    public function removeAt(int $index): never
    {
        invalid_mutation();
    }
}
