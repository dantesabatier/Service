<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use JsonSerializable;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\LLM\LLMAgentRuntime;
use stdClass;

/**
 * Fixes the canonical form the runtime hashes tool arguments into, which is what lets it recognise
 * a call the model already made. Two arguments that mean the same must canonicalize the same, and
 * two that differ must not.
 */
final class LLMAgentRuntimeCanonicalValueTest extends TestCase
{
    /** @throws ReflectionException */
    #[Test]
    public function aDictionaryIsTaggedAndItsKeysAreOrdered(): void
    {
        $this->assertSame(["dictionary", [["a", ["int", 1]], ["b", ["int", 2]]]], $this->canonical(new Dictionary(["b" => 2, "a" => 1])));
    }

    /** @throws ReflectionException */
    #[Test]
    public function twoDictionariesDifferingOnlyInKeyOrderCanonicalizeAlike(): void
    {
        $this->assertSame($this->canonical(new Dictionary(["a" => 1, "b" => 2])), $this->canonical(new Dictionary(["b" => 2, "a" => 1])));
    }

    /** @throws ReflectionException */
    #[Test]
    public function anArrayClassKeepsItsOrder(): void
    {
        $this->assertSame(["array", [["int", 1], ["int", 2]]], $this->canonical(new ArrayClass([1, 2])));
    }

    /** @throws ReflectionException */
    #[Test]
    public function orderMattersForASequence(): void
    {
        $this->assertNotSame($this->canonical(new ArrayClass([1, 2])), $this->canonical(new ArrayClass([2, 1])));
    }

    /** @throws ReflectionException */
    #[Test]
    public function anObjectShapeIsCanonicalizedAsADictionary(): void
    {
        $object = new stdClass();
        $object->b = 2;
        $object->a = 1;
        $this->assertSame($this->canonical(new Dictionary(["a" => 1, "b" => 2])), $this->canonical($object));
    }

    /** @throws ReflectionException */
    #[Test]
    public function aSerializableValueCarriesItsClassIntoTheCanonicalForm(): void
    {
        $canonical = $this->canonical(new CanonicalSerializableFixture(["a" => 1]));
        $this->assertSame("json", $canonical[0]);
        $this->assertSame(CanonicalSerializableFixture::class, $canonical[1]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function twoSerializablesWithTheSamePayloadButDifferentClassesDiffer(): void
    {
        $this->assertNotSame($this->canonical(new CanonicalSerializableFixture(["a" => 1])), $this->canonical(new OtherCanonicalSerializableFixture(["a" => 1])));
    }

    /** @throws ReflectionException */
    #[Test]
    public function aNativeListIsTaggedAsASequence(): void
    {
        $this->assertSame(["array", [["int", 1], ["int", 2]]], $this->canonical([1, 2]));
    }

    /** @throws ReflectionException */
    #[Test]
    public function aNativeMapIsTaggedAsADictionaryWithItsKeysOrdered(): void
    {
        $this->assertSame($this->canonical(["a" => 1, "b" => 2]), $this->canonical(["b" => 2, "a" => 1]));
    }

    /** @throws ReflectionException */
    #[Test]
    public function aNativeMapIsNotConfusedWithASequence(): void
    {
        $this->assertSame("dictionary", $this->canonical(["a" => 1])[0]);
        $this->assertSame("array", $this->canonical([1])[0]);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aScalarCarriesItsOwnTypeAlongsideItsValue(): void
    {
        $this->assertSame(["int", 7], $this->canonical(7));
        $this->assertSame(["string", "7"], $this->canonical("7"));
        $this->assertSame(["bool", true], $this->canonical(true));
        $this->assertSame(["float", 1.5], $this->canonical(1.5));
        $this->assertSame(["null", null], $this->canonical(null));
    }

    /** @throws ReflectionException */
    #[Test]
    public function aNumberAndItsStringAreNotTheSameArgument(): void
    {
        $this->assertNotSame($this->canonical(7), $this->canonical("7"));
    }

    /** @throws ReflectionException */
    #[Test]
    public function nestingIsCanonicalizedAllTheWayDown(): void
    {
        $nested = new Dictionary(["outer" => new ArrayClass([new Dictionary(["inner" => 1])])]);
        $this->assertSame(["dictionary", [["outer", ["array", [["dictionary", [["inner", ["int", 1]]]]]]]]], $this->canonical($nested));
    }

    /** @throws ReflectionException */
    #[Test]
    public function aDictionaryAndANativeMapCanonicalizeUnderDifferentShapes(): void
    {
        // A Dictionary emits [key, value] pairs while a native map keeps its keys, so the same data
        // in the two containers is not recognised as the same call. Both are stable in themselves.
        $this->assertSame(["dictionary", [["a", ["int", 1]]]], $this->canonical(new Dictionary(["a" => 1])));
        $this->assertSame(["dictionary", ["a" => ["int", 1]]], $this->canonical(["a" => 1]));
    }

    /**
     * @return array
     * @throws ReflectionException
     */
    private function canonical(mixed $value): array
    {
        $runtime = new ReflectionClass(LLMAgentRuntime::class)->newInstanceWithoutConstructor();
        /** @var array */
        return new ReflectionMethod(LLMAgentRuntime::class, "canonicalToolValue")->invoke($runtime, $value);
    }
}

class CanonicalSerializableFixture implements JsonSerializable
{
    /** @param array<string, mixed> $payload */
    public function __construct(private readonly array $payload)
    {
    }

    /** @return array<string, mixed> */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->payload;
    }
}

final class OtherCanonicalSerializableFixture extends CanonicalSerializableFixture
{
}
