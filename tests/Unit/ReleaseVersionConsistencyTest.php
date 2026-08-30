<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\PropertyListSerialization;
use Sabatier\Foundation\URL;
use const Sabatier\Foundation\kCFBundleShortVersionStringKey;
use const Sabatier\Service\MCPServerVersionDefault;

final class ReleaseVersionConsistencyTest extends TestCase
{
    #[Test]
    public function bundleAndMCPServerReportTheSameReleaseVersion(): void
    {
        $propertyList = PropertyListSerialization::propertyListWithURL(URL::fileURL(dirname(__DIR__, 2) . "/Info.plist"));
        $this->assertInstanceOf(Dictionary::class, $propertyList);
        $this->assertSame(MCPServerVersionDefault, $propertyList[kCFBundleShortVersionStringKey]);
    }
}
