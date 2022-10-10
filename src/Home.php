<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\HTTPRequestMethod;
use Sabatier\Foundation\HTTPStatusCode;
use Sabatier\Foundation\HTTPURLResponse;

use const Sabatier\Foundation\kCFBundleNameKey;
use const Sabatier\Foundation\kCFBundleShortVersionStringKey;
use const Sabatier\Foundation\kCFBundleVersionKey;

/** @internal */
class Home extends Endpoint
{
    public function route(): string
    {
        return '/';
    }

    public function allowedMethods(): ArrayClass
    {
        return new ArrayClass([HTTPRequestMethod::options, HTTPRequestMethod::get]);
    }

    public function requiresAuthentication(): bool
    {
        return false;
    }

    public function response(): HTTPURLResponse
    {
        $bundle = Bundle::main();
        /** @var string $name */
        $name = $bundle->object(kCFBundleNameKey);
        /** @var string $version */
        $version = $bundle->object(kCFBundleVersionKey);
        /** @var string $shortVersion */
        $shortVersion = $bundle->object(kCFBundleShortVersionStringKey);
        $this->content = sprintf("%s v%s (build %s)<br>Copyright © 2022, Dante Sabatier All rights reserved.", $name, $shortVersion, $version);
        return new HTTPURLResponse($this->url, HTTPStatusCode::ok, null, new Dictionary(["Content-Type" => "text/html; charset=utf-8"]));
    }
}
