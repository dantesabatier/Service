<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Set;

/** @internal */
class JSONWebTokenCoderStrategyFactory
{
    private static ?JSONWebTokenCoderStrategyFactory $shared = null;
    /** @var Set<class-string<JSONWebTokenEncoderStrategy>> $encoderStrategies */
    private(set) Set $encoderStrategies {
        get => $this->encoderStrategies ??= new Set();
    }
    /** @var Set<class-string<JSONWebTokenDecoderStrategy>> $decoderStrategies */
    private(set) Set $decoderStrategies {
        get => $this->decoderStrategies ??= new Set();
    }

    public static function shared(): JSONWebTokenCoderStrategyFactory
    {
        self::$shared ??= new self();
        return self::$shared;
    }

    /**
     * @param class-string<JSONWebTokenCoderStrategy> $strategyClass
     * @return bool
     */
    public function register(string $strategyClass): bool
    {
        if (!is_subclass_of($strategyClass, JSONWebTokenCoderStrategy::class)) {
            return false;
        }
        if (is_subclass_of($strategyClass, JSONWebTokenEncoderStrategy::class)) {
            $this->encoderStrategies[] = $strategyClass;
        } elseif (is_subclass_of($strategyClass, JSONWebTokenDecoderStrategy::class)) {
            $this->decoderStrategies[] = $strategyClass;
        }
        return true;
    }

    /**
     * @template T of JSONWebTokenCoderStrategy
     * @param Set<class-string<T>> $strategyClasses
     * @param JSONWebTokenSigningAlgorithm $algorithm
     * @return class-string<T>
     */
    public function getStrategyClass(Set $strategyClasses, JSONWebTokenSigningAlgorithm $algorithm): string
    {
        return $strategyClasses->first(
        /**
         * @param class-string<T> $strategyClass
         * @return bool
         */
            fn(string $strategyClass) => $strategyClass::isSupported($algorithm)
        ) ?? throw new UnimplementedException("Unsupported algorithm: $algorithm->value");
    }

    /**
     * @param class-string<JSONWebTokenCoderStrategy> $strategyClass
     */
    public function unregister(string $strategyClass): void
    {
        if (is_subclass_of($strategyClass, JSONWebTokenEncoderStrategy::class)) {
            $this->encoderStrategies->remove($strategyClass);
        } elseif (is_subclass_of($strategyClass, JSONWebTokenDecoderStrategy::class)) {
            $this->decoderStrategies->remove($strategyClass);
        }
    }
}
