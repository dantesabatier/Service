<?php

namespace Sabatier\Service;

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

    public function __construct(Responder $responder, Request $request)
    {
        $this->decorators = new Set();
        $path = $request->url->path;
        $reflectionClass = new ReflectionClass($responder);
        foreach ($reflectionClass->getAttributes(Endpoint::class) as $attribute) {
            $endpoint = $attribute->newInstance();
            $other = $endpoint->path ?? "/{$reflectionClass->getShortName()}";
            if (!str_starts_with($other, "/")) {
                $other = "/$other";
            }
            if (string_is_equal($path, $other, CompareOptions::caseInsensitive)) {
                $this->matches = true;
                $this->decorators->appendContentsOf($endpoint->decorators);
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
                    $this->decorators->appendContentsOf($action->decorators);
                    break 2;
                }
            }
        }
        $this->decorators->append(ResponseHeaderSanitizerDecorator::class);
    }
}
