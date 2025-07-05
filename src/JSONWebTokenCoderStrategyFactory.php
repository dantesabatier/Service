<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Set;

/** @internal */
class JSONWebTokenCoderStrategyFactory
{
    private static ?JSONWebTokenCoderStrategyFactory $shared = null;
    /** @var Set<class-string<covariant JSONWebTokenCoderStrategy>> $strategies */
    private(set) Set $strategies {
        get => $this->strategies ??= new Set();
    }
    /** @var Set<class-string<covariant JSONWebTokenEncoderStrategy>> $encoderStrategies */
    public Set $encoderStrategies {
        get => $this->computedStrategiesOfClass(JSONWebTokenEncoderStrategy::class);
    }
    /** @var Set<class-string<covariant JSONWebTokenDecoderStrategy>> $decoderStrategies */
    public Set $decoderStrategies {
        get => $this->computedStrategiesOfClass(JSONWebTokenDecoderStrategy::class);
    }

    /**
     * @template T of JSONWebTokenCoderStrategy
     * @param class-string<T> $strategyClass
     * @return Set<class-string<T>>
     */
    private function computedStrategiesOfClass(string $strategyClass): Set
    {
        /** @var Set<class-string<T>> */
        return $this->strategies->filter(fn(string $strategy) => is_subclass_of($strategy, $strategyClass));
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
        $this->strategies[] = $strategyClass;
        return true;
    }

    /**
     * @template T of JSONWebTokenCoderStrategy
     * @param Set<class-string<T>> $strategyClasses
     * @param JSONWebTokenSigningAlgorithm $algorithm
     * @return class-string<T>|null
     */
    public function getStrategyClass(Set $strategyClasses, JSONWebTokenSigningAlgorithm $algorithm): ?string
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
