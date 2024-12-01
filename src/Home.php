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
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::head, HTTPRequestMethod::options]);
    }
    public bool $isProtectedContentAvailable = true;
    public Bundle $bundle {
        get => $this->bundle ??= Bundle::bundleForClass(self::class);
    }
    #[Outlet]
    public ?string $title = null {
        get => $this->title ??= Bundle::main()->object(kCFBundleNameKey);
    }
    #[Outlet]
    public ?string $version {
        get => Bundle::main()->object(kCFBundleVersionKey);
    }
    #[Outlet]
    public ?string $shortVersion {
        get => Bundle::main()->object(kCFBundleShortVersionStringKey);
    }
    #[Outlet]
    public ?string $copyright {
        get => Bundle::main()->object(kCFBundleHumanReadableCopyright);
    }
}
