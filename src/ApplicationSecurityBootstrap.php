<?php

declare(strict_types=1);

namespace Sabatier\Service;

/** @internal */
final class ApplicationSecurityBootstrap
{
    public static function boot(): void
    {
        AuthenticationRegistrar::register();
        JSONWebTokenCoderStrategyRegistrar::register();
    }
}
