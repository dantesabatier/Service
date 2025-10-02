<?php

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
