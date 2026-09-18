<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sabatier\Foundation\Set;
use Sabatier\Service\JSONWebTokenCoderStrategyFactory;
use Sabatier\Service\JSONWebTokenCoderStrategyRegistrar;
use Sabatier\Service\JSONWebTokenHS256DecoderStrategy;
use Sabatier\Service\JSONWebTokenHS256EncoderStrategy;
use Sabatier\Service\JSONWebTokenRS256DecoderStrategy;
use Sabatier\Service\JSONWebTokenRS256EncoderStrategy;
use Sabatier\Service\JSONWebTokenSigningAlgorithm;
use Sabatier\Service\UnimplementedException;

final class JSONWebTokenCoderStrategyFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetFactory();
    }

    protected function tearDown(): void
    {
        $this->resetFactory();
        JSONWebTokenCoderStrategyRegistrar::register();
        parent::tearDown();
    }

    #[Test]
    public function theFactoryIsShared(): void
    {
        $this->assertSame(JSONWebTokenCoderStrategyFactory::shared(), JSONWebTokenCoderStrategyFactory::shared());
    }

    #[Test]
    public function aFreshFactoryKnowsNoStrategies(): void
    {
        $factory = JSONWebTokenCoderStrategyFactory::shared();
        $this->assertTrue($factory->encoderStrategies->isEmpty);
        $this->assertTrue($factory->decoderStrategies->isEmpty);
    }

    #[Test]
    public function anEncoderIsFiledUnderTheEncoders(): void
    {
        $factory = JSONWebTokenCoderStrategyFactory::shared();
        $this->assertTrue($factory->register(JSONWebTokenHS256EncoderStrategy::class));
        $this->assertTrue($factory->encoderStrategies->containsElement(JSONWebTokenHS256EncoderStrategy::class));
        $this->assertTrue($factory->decoderStrategies->isEmpty);
    }

    #[Test]
    public function aDecoderIsFiledUnderTheDecoders(): void
    {
        $factory = JSONWebTokenCoderStrategyFactory::shared();
        $this->assertTrue($factory->register(JSONWebTokenHS256DecoderStrategy::class));
        $this->assertTrue($factory->decoderStrategies->containsElement(JSONWebTokenHS256DecoderStrategy::class));
        $this->assertTrue($factory->encoderStrategies->isEmpty);
    }

    #[Test]
    public function aClassThatIsNotACoderStrategyIsRefused(): void
    {
        $factory = JSONWebTokenCoderStrategyFactory::shared();
        $this->assertFalse($factory->register(Set::class));
        $this->assertTrue($factory->encoderStrategies->isEmpty);
        $this->assertTrue($factory->decoderStrategies->isEmpty);
    }

    #[Test]
    public function registeringTheSameStrategyTwiceFilesItOnce(): void
    {
        $factory = JSONWebTokenCoderStrategyFactory::shared();
        $factory->register(JSONWebTokenHS256EncoderStrategy::class);
        $factory->register(JSONWebTokenHS256EncoderStrategy::class);
        $this->assertSame(1, $factory->encoderStrategies->count);
    }

    #[Test]
    public function theStrategyForAnAlgorithmIsTheOneThatSupportsIt(): void
    {
        JSONWebTokenCoderStrategyRegistrar::register();
        $factory = JSONWebTokenCoderStrategyFactory::shared();
        $this->assertSame(JSONWebTokenHS256EncoderStrategy::class, $factory->getStrategyClass($factory->encoderStrategies, JSONWebTokenSigningAlgorithm::hs256));
        $this->assertSame(JSONWebTokenRS256DecoderStrategy::class, $factory->getStrategyClass($factory->decoderStrategies, JSONWebTokenSigningAlgorithm::rs256));
    }

    #[Test]
    public function anAlgorithmNoStrategySupportsIsRefused(): void
    {
        $factory = JSONWebTokenCoderStrategyFactory::shared();
        $factory->register(JSONWebTokenHS256EncoderStrategy::class);
        $this->expectException(UnimplementedException::class);
        $factory->getStrategyClass($factory->encoderStrategies, JSONWebTokenSigningAlgorithm::rs256);
    }

    #[Test]
    public function theRefusalNamesTheAlgorithmItCouldNotServe(): void
    {
        $factory = JSONWebTokenCoderStrategyFactory::shared();
        try {
            $factory->getStrategyClass($factory->encoderStrategies, JSONWebTokenSigningAlgorithm::rs256);
            $this->fail("An unsupported algorithm must be refused.");
        } catch (UnimplementedException $exception) {
            $this->assertStringContainsString(JSONWebTokenSigningAlgorithm::rs256->value, (string)$exception->error->localizedFailureReason);
        }
    }

    #[Test]
    public function theRegistrarFilesEveryShippedStrategy(): void
    {
        JSONWebTokenCoderStrategyRegistrar::register();
        $factory = JSONWebTokenCoderStrategyFactory::shared();
        $this->assertTrue($factory->encoderStrategies->containsElement(JSONWebTokenHS256EncoderStrategy::class));
        $this->assertTrue($factory->encoderStrategies->containsElement(JSONWebTokenRS256EncoderStrategy::class));
        $this->assertTrue($factory->decoderStrategies->containsElement(JSONWebTokenHS256DecoderStrategy::class));
        $this->assertTrue($factory->decoderStrategies->containsElement(JSONWebTokenRS256DecoderStrategy::class));
    }

    #[Test]
    public function theRegistrarRunsOnlyOnce(): void
    {
        JSONWebTokenCoderStrategyRegistrar::register();
        $factory = JSONWebTokenCoderStrategyFactory::shared();
        $factory->encoderStrategies->removeAll();
        JSONWebTokenCoderStrategyRegistrar::register();
        $this->assertTrue($factory->encoderStrategies->isEmpty);
    }

    private function resetFactory(): void
    {
        new ReflectionProperty(JSONWebTokenCoderStrategyFactory::class, "shared")->setValue(null, null);
        new ReflectionProperty(JSONWebTokenCoderStrategyRegistrar::class, "registered")->setValue(null, false);
    }
}
