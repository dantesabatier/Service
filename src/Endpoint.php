<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\HTTPRequestMethod;
use Sabatier\Foundation\HTTPURLResponse;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\URL;

/**
 * Class Endpoint
 * @package Sabatier\Service
 */
abstract class Endpoint extends ObjectClass
{
    public readonly URL $url;
    protected ?string $content = null;

    public function __construct(public readonly Service $service)
    {
        $this->url = new URL($this->route(), $this->service->request->url);
    }

    public function name(): string
    {
        return $this::className();
    }

    public function route(): string
    {
        return "/{$this->name()}";
    }

    /**
     * @return ArrayClass<string>
     */
    public function allowedMethods(): ArrayClass
    {
        return new ArrayClass([HTTPRequestMethod::head, HTTPRequestMethod::options, HTTPRequestMethod::get, HTTPRequestMethod::post, HTTPRequestMethod::patch, HTTPRequestMethod::put, HTTPRequestMethod::delete]);
    }

    public function isSecure(): bool
    {
        return true;
    }

    /**
     * @throws Exception
     */
    public function response(): HTTPURLResponse
    {
        return new HTTPURLResponse($this->url);
    }

    public function content(): ?string
    {
        return $this->content;
    }
}
