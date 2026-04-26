<?php

declare(strict_types=1);

namespace Sabatier\Service;

/** @internal */
final class AuthenticationRegistrar
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        $classes = [BasicAuthentication::class, BearerAuthentication::class, DigestAuthentication::class];
        foreach ($classes as $class) {
            AuthenticationResolver::registerClass($class);
        }
        self::$registered = true;
    }
}
