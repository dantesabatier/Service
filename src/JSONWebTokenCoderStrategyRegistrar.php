<?php

namespace Sabatier\Service;

/** @internal */
final class JSONWebTokenCoderStrategyRegistrar
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        $classes = [JSONWebTokenHS256EncoderStrategy::class, JSONWebTokenHS256DecoderStrategy::class, JSONWebTokenRS256EncoderStrategy::class, JSONWebTokenRS256DecoderStrategy::class];
        $factory = JSONWebTokenCoderStrategyFactory::shared();
        foreach ($classes as $class) {
            $factory->register($class);
        }
        self::$registered = true;
    }
}
