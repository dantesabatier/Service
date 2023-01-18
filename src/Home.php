<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use const Sabatier\Foundation\kCFBundleHumanReadableCopyright;
use const Sabatier\Foundation\kCFBundleShortVersionStringKey;
use const Sabatier\Foundation\kCFBundleVersionKey;

/** @internal */
#[Endpoint("/")]
class Home extends ViewController
{
    #[Outlet]
    public readonly ?string $version;
    #[Outlet]
    public readonly ?string $shortVersion;
    #[Outlet]
    public readonly ?string $copyright;

    public function __construct()
    {
        parent::__construct();
        $this->bundle = Bundle::bundleForClass(self::class);
        $this->version = $this->bundle->object(kCFBundleVersionKey);
        $this->shortVersion = $this->bundle->object(kCFBundleShortVersionStringKey);
        $this->copyright = $this->bundle->object(kCFBundleHumanReadableCopyright);
        $this->allowedMethods = new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::head, HTTPRequestMethod::options]);
        $this->isProtectedContentAvailable = true;
    }
}
