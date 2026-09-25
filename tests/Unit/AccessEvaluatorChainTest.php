<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use Exception;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\AccessEvaluationContext;
use Sabatier\Service\AccessEvaluator;
use Sabatier\Service\AccessEvaluatorChain;

final class AccessEvaluatorChainTest extends TestCase
{
    /** @throws ReflectionException */
    private function context(): AccessEvaluationContext
    {
        return new ReflectionClass(AccessEvaluationContext::class)->newInstanceWithoutConstructor();
    }

    private function allow(): AccessEvaluator
    {
        return new class() implements AccessEvaluator {
            #[Override]
            public function evaluate(AccessEvaluationContext $context): bool { return true; }
        };
    }

    private function deny(): AccessEvaluator
    {
        return new class() implements AccessEvaluator {
            #[Override]
            public function evaluate(AccessEvaluationContext $context): bool { return false; }
        };
    }

    /** @throws Exception */
    #[Test]
    public function emptyChainGrantsAccess(): void
    {
        $chain = new AccessEvaluatorChain(new ArrayClass());
        $this->assertTrue($chain->evaluate($this->context()));
    }

    /** @throws Exception */
    #[Test]
    public function singleAllowingEvaluatorGrantsAccess(): void
    {
        $chain = new AccessEvaluatorChain(new ArrayClass([$this->allow()]));
        $this->assertTrue($chain->evaluate($this->context()));
    }

    /** @throws Exception */
    #[Test]
    public function singleDenyingEvaluatorDeniesAccess(): void
    {
        $chain = new AccessEvaluatorChain(new ArrayClass([$this->deny()]));
        $this->assertFalse($chain->evaluate($this->context()));
    }

    /** @throws Exception */
    #[Test]
    public function failedEvaluatorIsNullWhenAllAllow(): void
    {
        $chain = new AccessEvaluatorChain(new ArrayClass([$this->allow(), $this->allow()]));
        $chain->evaluate($this->context());
        $this->assertNull($chain->failedEvaluator);
    }

    /** @throws Exception */
    #[Test]
    public function failedEvaluatorIsSetOnDenial(): void
    {
        $deny = $this->deny();
        $chain = new AccessEvaluatorChain(new ArrayClass([$this->allow(), $deny]));
        $chain->evaluate($this->context());
        $this->assertSame($deny, $chain->failedEvaluator);
    }

    /** @throws Exception */
    #[Test]
    public function failedEvaluatorTracksFirstDenialInSequence(): void
    {
        $first = $this->deny();
        $second = $this->deny();
        $chain = new AccessEvaluatorChain(new ArrayClass([$first, $second]));
        $chain->evaluate($this->context());
        $this->assertSame($first, $chain->failedEvaluator);
    }

    /** @throws Exception */
    #[Test]
    public function allMustAllowForAccess(): void
    {
        $chain = new AccessEvaluatorChain(new ArrayClass([$this->allow(), $this->allow(), $this->deny()]));
        $this->assertFalse($chain->evaluate($this->context()));
    }

    /** @throws Exception */
    #[Test]
    public function firstDenialShortCircuitsChain(): void
    {
        $state = (object)["called" => false];
        $sentinel = new class($state) implements AccessEvaluator {
            public function __construct(private readonly object $state) {}
            #[Override]
            public function evaluate(AccessEvaluationContext $context): bool {
                $this->state->called = true;
                return true;
            }
        };
        $chain = new AccessEvaluatorChain(new ArrayClass([$this->deny(), $sentinel]));
        $chain->evaluate($this->context());
        $this->assertFalse($state->called);
    }
}
