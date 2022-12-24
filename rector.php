<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\Php73\Rector\FuncCall\JsonThrowOnErrorRector;
use Rector\Php80\Rector\FunctionLike\MixedTypeRector;
use Rector\Php80\Rector\FunctionLike\UnionTypesRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnNeverTypeRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . "/src"
    ]);
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_82,
        SetList::CODE_QUALITY,
    ]);
    $rectorConfig->skip([
        ExplicitBoolCompareRector::class,
        JsonThrowOnErrorRector::class => [
            __DIR__ . "/src/Responder.php",
            __DIR__ . "/src/Service.php",
        ],
        ReadOnlyPropertyRector::class => [
            __DIR__ . "src/Action.php",
            __DIR__ . "src/Endpoint.php",
        ],
        ReturnNeverTypeRector::class,
        NullToStrictStringFuncCallArgRector::class,
        UnionTypesRector::class,
        MixedTypeRector::class,
        RemoveUselessParamTagRector::class,
        RemoveUselessReturnTagRector::class
    ]);
    $rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);
    $rectorConfig->disableParallel();
};
