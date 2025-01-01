<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\ExplicitReturnNullRector;
use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPromotedPropertyRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\Exception\Configuration\InvalidConfigurationException;
use Rector\Php73\Rector\ConstFetch\SensitiveConstantNameRector;
use Rector\Php74\Rector\Property\RestoreDefaultNullToNullableTypePropertyRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php81\Rector\ClassMethod\NewInInitializerRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;

try {
    return RectorConfig::configure()
        ->withPaths([
            __DIR__ . "/src",
        ])->withPhpSets()->withSkip([
            SensitiveConstantNameRector::class,
            ClassPropertyAssignToConstructorPromotionRector::class,
            NewInInitializerRector::class,
            ExplicitBoolCompareRector::class,
            FlipTypeControlToUseExclusiveTypeRector::class,
            DisallowedEmptyRuleFixerRector::class,
            LocallyCalledStaticMethodToNonStaticRector::class,
            ExplicitReturnNullRector::class,
            RemoveUselessReturnTagRector::class,
            ReadOnlyPropertyRector::class,
            RestoreDefaultNullToNullableTypePropertyRector::class,
            RemoveUselessParamTagRector::class,
            RemoveEmptyClassMethodRector::class => [
                __DIR__ . "/src/IdentityManager.php"
            ],
            RemoveUnusedPromotedPropertyRector::class => [
                __DIR__ . "/src/IdentityManager.php"
            ],
        ])->withPreparedSets(deadCode: true, codeQuality: true, earlyReturn: true);
} catch (InvalidConfigurationException $e) {
    error_log($e->getMessage());
}
