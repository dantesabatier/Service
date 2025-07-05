<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;

/** @internal */
class JSONWebTokenCoderStrategyFactory
{
    private static ?JSONWebTokenCoderStrategyFactory $shared = null;
    /** @var ArrayClass<class-string<covariant JSONWebTokenCoderStrategy>> $strategies */
    private(set) ArrayClass $strategies {
        get => $this->strategies ??= new ArrayClass();
    }
    /** @var ArrayClass<class-string<covariant JSONWebTokenEncoderStrategy>> $encoderStrategies */
    public ArrayClass $encoderStrategies {
        get => $this->strategies->filter(fn(string $strategy) => is_subclass_of($strategy, JSONWebTokenEncoderStrategy::class));
    }
    /** @var ArrayClass<class-string<covariant JSONWebTokenDecoderStrategy>> $decoderStrategies */
    public ArrayClass $decoderStrategies {
        get => $this->strategies->filter(fn(string $strategy) => is_subclass_of($strategy, JSONWebTokenDecoderStrategy::class));
    }

    public static function shared(): JSONWebTokenCoderStrategyFactory
    {
        self::$shared ??= new self();
        return self::$shared;
    }

    /**
     * @param class-string<covariant JSONWebTokenCoderStrategy> $strategyClass
     * @return bool
     */
    public function register(string $strategyClass): bool
    {
        if (!is_subclass_of($strategyClass, JSONWebTokenCoderStrategy::class)) {
            return false;
        }
        if (!$this->strategies->containsElement($strategyClass)) {
            $this->strategies[] = $strategyClass;
        }
        return true;
    }

    /**
     * @template T of JSONWebTokenCoderStrategy
     * @param ArrayClass<class-string<T>> $strategyClasses
     * @param JSONWebTokenSigningAlgorithm $algorithm
     * @return class-string<T>|null
     */
    public function getStrategyClass(ArrayClass $strategyClasses, JSONWebTokenSigningAlgorithm $algorithm): ?string
    {
        return $strategyClasses->first(
        /**
         * @param class-string<T> $strategyClass
         * @return bool
         */
            fn(string $strategyClass) => $strategyClass::$algorithm === $algorithm
        );
    }

    /**
     * @param class-string<covariant JSONWebTokenCoderStrategy> $strategyClass
     */
    public function unregister(string $strategyClass): void
    {
        $this->strategies->remove($strategyClass);
    }
}
