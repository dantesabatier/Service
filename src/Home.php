<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use const Sabatier\Foundation\kCFBundleHumanReadableCopyright;
use const Sabatier\Foundation\kCFBundleNameKey;
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
        $this->allowedMethods = new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::head, HTTPRequestMethod::options]);
        $this->isProtectedContentAvailable = true;
    }

    public function viewWillLoad(): void
    {
        $bundle = Bundle::main();
        $this->title = $bundle->object(kCFBundleNameKey);
        $this->version = $bundle->object(kCFBundleVersionKey);
        $this->shortVersion = $bundle->object(kCFBundleShortVersionStringKey);
        $this->copyright = $bundle->object(kCFBundleHumanReadableCopyright);
        $this->bundle = Bundle::bundleForClass(self::class);
    }
}
