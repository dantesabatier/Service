<?php

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use const Sabatier\Foundation\kCFBundleHumanReadableCopyright;
use const Sabatier\Foundation\kCFBundleNameKey;
use const Sabatier\Foundation\kCFBundleShortVersionStringKey;
use const Sabatier\Foundation\kCFBundleVersionKey;

/** @internal */
#[Endpoint("/")]
final class HomeController extends ViewController
{
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get]);
    }
    #[Override]
    protected string $name = "Home";
    #[Override]
    public bool $isProtectedContentAvailable = true;
    #[Override]
    protected Bundle $bundle {
        get => $this->bundle ??= Bundle::bundleForClass(self::class);
    }
    #[Outlet]
    #[Override]
    public ?string $title {
        get => Bundle::main()->object(kCFBundleNameKey);
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
