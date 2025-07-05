<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;

/** @internal */
class JSONWebTokenStrategyFactory
{
    /** @var ArrayClass<class-string<JSONWebTokenCoderStrategy>>|null */
    private static ?ArrayClass $strategies = null;

    /**
     * @return ArrayClass<class-string<JSONWebTokenCoderStrategy>>
     */
    private static function strategies(): ArrayClass
    {
        self::$strategies ??= new ArrayClass();
        return self::$strategies;
    }

    /**
     * @return ArrayClass<class-string<JSONWebTokenCoderStrategy>>|null
     * @internal
     */
    public static function getStrategies(): ?ArrayClass
    {
        return self::$strategies;
    }

    /**
     * @return ArrayClass<class-string<JSONWebTokenEncoderStrategy>>|null
     * @internal
     */
    public static function getEncoderStrategies(): ?ArrayClass
    {
        /** @var ArrayClass<class-string<JSONWebTokenEncoderStrategy>>|null */
        return self::$strategies?->filter(fn(string $strategy) => is_subclass_of($strategy, JSONWebTokenEncoderStrategy::class));
    }

    /**
     * @return ArrayClass<class-string<JSONWebTokenDecoderStrategy>>|null
     * @internal
     */
    public static function getDecoderStrategies(): ?ArrayClass
    {
        /** @var ArrayClass<class-string<JSONWebTokenDecoderStrategy>>|null */
        return self::$strategies?->filter(fn(string $strategy) => is_subclass_of($strategy, JSONWebTokenDecoderStrategy::class));
    }

    /**
     * @param class-string<covariant JSONWebTokenCoderStrategy> $strategyClass
     * @return bool
     */
    public static function registerClass(string $strategyClass): bool
    {
        if (!is_subclass_of($strategyClass, JSONWebTokenCoderStrategy::class)) {
            return false;
        }
        $strategies = self::strategies();
        if (!$strategies->containsElement($strategyClass)) {
            $strategies[] = $strategyClass;
        }
        return true;
    }

    /**
     * @template T of JSONWebTokenCoderStrategy
     * @param ArrayClass<class-string<T>> $strategyClasses
     * @param JSONWebTokenSigningAlgorithm $algorithm
     * @return class-string<T>|null
     */
    public static function getStrategyClass(ArrayClass $strategyClasses, JSONWebTokenSigningAlgorithm $algorithm): ?string
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
     * @param class-string<JSONWebTokenCoderStrategy> $strategyClass
     */
    public static function unregisterClass(string $strategyClass): void
    {
        if ($strategies = self::$strategies) {
            $strategies->remove($strategyClass);
        }
    }
}
