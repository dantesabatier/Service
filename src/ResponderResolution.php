<?php

namespace Sabatier\Service;

use Exception;
use ReflectionClass;
use ReflectionMethod;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URLComponents;
use function Sabatier\Foundation\is_parseable_url;
use function Sabatier\Foundation\string_is_equal;

/** @internal */
final class ResponderResolution
{
    private(set) bool $matches = false;
    private(set) ?string $selector = null;
    /** @var Set<class-string<ResponseTransformer>> */
    private(set) Set $transformers;

    /**
     * @param class-string<Responder> $responderClass
     * @param string $path
     * @throws Exception
     */
    public function __construct(string $responderClass, string $path)
    {
        $this->transformers = new Set();
        $reflectionClass = new ReflectionClass($responderClass);
        foreach ($reflectionClass->getAttributes(Endpoint::class) as $attribute) {
            $endpoint = $attribute->newInstance();
            $other = $endpoint->path ?? "/{$reflectionClass->getShortName()}";
            if (!str_starts_with($other, "/")) {
                $other = "/$other";
            }
            if (string_is_equal($path, $other, CompareOptions::caseInsensitive)) {
                $this->matches = true;
                $this->transformers->formUnion($endpoint->transformers);
            }
        }
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(Action::class) as $attribute) {
                $action = $attribute->newInstance();
                $other = $action->path ?? "/$method->name";
                if (is_parseable_url($other)) {
                    $components = new URLComponents($other);
                    $other = "$components->path$components->query";
                }
                if (string_is_equal($path, $other, CompareOptions::caseInsensitive)) {
                    $this->matches = true;
                    $this->selector = $method->name;
                    $this->transformers->formUnion($action->transformers);
                    break 2;
                }
            }
        }
        $this->transformers->insert(ResponseHeaderSanitizerTransformer::class);
    }
}
