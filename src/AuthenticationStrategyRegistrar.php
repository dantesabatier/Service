<?php

namespace Sabatier\Service;

/** @internal */
final class AuthenticationStrategyRegistrar
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        $classes = [BasicAuthenticationStrategy::class, BearerAuthenticationStrategy::class, DigestAuthenticationStrategy::class];
        foreach ($classes as $class) {
            AuthenticationStrategyFactory::registerClass($class);
        }
        self::$registered = true;
    }
}
