<?php

namespace Sabatier\Service;

use Exception;
use ReflectionClass;
use ReflectionMethod;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URLComponents;
use function Sabatier\Foundation\string_is_equal;
use function Sabatier\Foundation\url_validate;

/** @internal */
final class ResponderResolution
{
    private(set) bool $matches = false;
    private(set) ?string $selector = null;
    /** @var Set<class-string<ResponseDecorator>> */
    private(set) Set $decorators;

    /**
     * @param class-string<Responder> $responderClass
     * @param string $path
     * @throws Exception
     */
    public function __construct(string $responderClass, string $path)
    {
        $this->decorators = new Set();
        $reflectionClass = new ReflectionClass($responderClass);
        foreach ($reflectionClass->getAttributes(Endpoint::class) as $attribute) {
            $endpoint = $attribute->newInstance();
            $other = $endpoint->path ?? "/{$reflectionClass->getShortName()}";
            if (!str_starts_with($other, "/")) {
                $other = "/$other";
            }
            if (string_is_equal($path, $other, CompareOptions::caseInsensitive)) {
                $this->matches = true;
                $this->decorators->formUnion($endpoint->decorators);
            }
        }
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(Action::class) as $attribute) {
                $action = $attribute->newInstance();
                $other = $action->path ?? "/$method->name";
                if (url_validate($other)) {
                    $components = new URLComponents($other);
                    $other = "$components->path$components->query";
                }
                if (string_is_equal($path, $other, CompareOptions::caseInsensitive)) {
                    $this->matches = true;
                    $this->selector = $method->name;
                    $this->decorators->formUnion($action->decorators);
                    break 2;
                }
            }
        }
        $this->decorators->insert(ResponseHeaderSanitizerDecorator::class);
        if ($reflectionClass->isSubclassOf(ViewController::class)) {
            $this->decorators->insert(HTMLDecorator::class);
        }
    }
}
