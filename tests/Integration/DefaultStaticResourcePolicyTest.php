<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Exception;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\URL;
use Sabatier\Service\DefaultStaticResourcePolicy;
use const Sabatier\Service\StaticResourceMaxAgeDefault;
use const Sabatier\Service\StaticResourceOptionalMaxAgeDefault;

/**
 * Exercises DefaultStaticResourcePolicy against the real filesystem, mirroring how
 * ResourceManager resolves a request path into an absolute resource URL.
 */
final class DefaultStaticResourcePolicyTest extends TestCase
{
    private DefaultStaticResourcePolicy $policy;
    /** @var list<string> */
    private array $createdFiles = [];
    /** @var list<string> */
    private array $createdDirectories = [];
    private bool $envWritten = false;

    #[Override]
    protected function setUp(): void
    {
        $this->policy = new DefaultStaticResourcePolicy();
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        foreach ($this->createdDirectories as $directory) {
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
        if ($this->envWritten) {
            $env = "{$this->documentRoot()}.env";
            if (is_file($env)) {
                unlink($env);
            }
            $this->resetProcessInfo();
        }
    }

    /**
     * Writes a temporary `.env` at the document root and clears the cached ProcessInfo
     * singleton so the policy re-reads the environment. Restored in tearDown.
     */
    private function writeEnv(string $contents): void
    {
        file_put_contents("{$this->documentRoot()}.env", $contents);
        $this->envWritten = true;
        $this->resetProcessInfo();
    }

    private function resetProcessInfo(): void
    {
        $property = new ReflectionProperty(ProcessInfo::class, "processInfo");
        $property->setValue(null, null);
    }

    private function documentRoot(): string
    {
        return rtrim(FileManager::default()->documentRootDirectory->path, "/") . "/";
    }

    private function resolve(string $requestPath): URL
    {
        return FileManager::default()->documentRootDirectory->appendingPathComponent($requestPath);
    }

    private function writeFile(string $absolutePath): void
    {
        $directory = dirname($absolutePath);
        $missing = [];
        for ($current = $directory; !is_dir($current); $current = dirname($current)) {
            $missing[] = $current;
        }
        if ($missing !== []) {
            mkdir($directory, 0777, true);
            foreach ($missing as $created) {
                $this->createdDirectories[] = $created;
            }
        }
        file_put_contents($absolutePath, "/* fixture */");
        $this->createdFiles[] = $absolutePath;
    }

    // --- Recurso del bundle (directorio público) ---

    /** @throws Exception */
    #[Test]
    public function publicBundleResourceIsCacheableImmutableWithLongMaxAge(): void
    {
        $this->writeFile("{$this->documentRoot()}vendor/__policy_probe_asset.css");
        $disposition = $this->policy->evaluate($this->resolve("/vendor/__policy_probe_asset.css"));
        $this->assertTrue($disposition->shouldHandle);
        $this->assertTrue($disposition->cacheable);
        $this->assertTrue($disposition->immutable);
        $this->assertSame(StaticResourceMaxAgeDefault, $disposition->maxAge);
    }

    // --- Recurso fuera de directorios públicos ---

    /** @throws Exception */
    #[Test]
    public function nonPublicResourceIsHandledButNotCacheable(): void
    {
        $this->writeFile("{$this->documentRoot()}tests/Fixtures/__policy_probe_private.css");
        $disposition = $this->policy->evaluate($this->resolve("/tests/Fixtures/__policy_probe_private.css"));
        $this->assertTrue($disposition->shouldHandle);
        $this->assertFalse($disposition->cacheable);
        $this->assertFalse($disposition->immutable);
        $this->assertSame(0, $disposition->maxAge);
    }

    // --- Recurso optativo del navegador ---

    /** @throws Exception */
    #[Test]
    public function optionalBrowserResourceIsCacheableWithShortMaxAgeAndMutable(): void
    {
        $disposition = $this->policy->evaluate($this->resolve("/favicon.ico"));
        $this->assertTrue($disposition->isOptional);
        $this->assertTrue($disposition->cacheable);
        $this->assertFalse($disposition->immutable);
        $this->assertSame(StaticResourceOptionalMaxAgeDefault, $disposition->maxAge);
    }

    // --- Recurso inexistente y no optativo ---

    /** @throws Exception */
    #[Test]
    public function missingNonOptionalResourceIsNotHandled(): void
    {
        $disposition = $this->policy->evaluate($this->resolve("/__policy_probe_missing.js"));
        $this->assertFalse($disposition->shouldHandle);
        $this->assertFalse($disposition->cacheable);
    }

    // --- Directorio público extra declarado por STATIC_PUBLIC_DIRECTORIES ---

    /** @throws Exception */
    #[Test]
    public function resourceInConfiguredPublicDirectoryIsCacheableImmutable(): void
    {
        $this->writeFile("{$this->documentRoot()}__PolicyProbeBuild/assets/app-ABCD1234.js");
        $this->writeEnv("STATIC_PUBLIC_DIRECTORIES=__PolicyProbeBuild");
        $disposition = new DefaultStaticResourcePolicy()->evaluate($this->resolve("/__PolicyProbeBuild/assets/app-ABCD1234.js"));
        $this->assertTrue($disposition->cacheable);
        $this->assertTrue($disposition->immutable);
        $this->assertSame(StaticResourceMaxAgeDefault, $disposition->maxAge);
    }

    /** @throws Exception */
    #[Test]
    public function resourceInUnconfiguredDirectoryIsNotCacheable(): void
    {
        $this->writeFile("{$this->documentRoot()}__PolicyProbeBuild/assets/app-ABCD1234.js");
        $this->writeEnv("STATIC_PUBLIC_DIRECTORIES=SomeOtherDir");
        $disposition = new DefaultStaticResourcePolicy()->evaluate($this->resolve("/__PolicyProbeBuild/assets/app-ABCD1234.js"));
        $this->assertTrue($disposition->shouldHandle);
        $this->assertFalse($disposition->cacheable);
    }

    /** @throws Exception */
    #[Test]
    public function existingHiddenFileIsNotHandled(): void
    {
        $this->writeFile("{$this->documentRoot()}.__policy_probe_hidden");
        $disposition = $this->policy->evaluate($this->resolve("/.__policy_probe_hidden"));
        $this->assertFalse($disposition->shouldHandle);
        $this->assertFalse($disposition->cacheable);
        $this->assertFalse($disposition->allowEmptyResponse);
    }

    /** @throws Exception */
    #[Test]
    public function fileUnderHiddenDirectoryIsNotHandled(): void
    {
        $this->writeFile("{$this->documentRoot()}.__policy_probe_dir/HEAD");
        $disposition = $this->policy->evaluate($this->resolve("/.__policy_probe_dir/HEAD"));
        $this->assertFalse($disposition->shouldHandle);
        $this->assertFalse($disposition->cacheable);
    }

    /** @throws Exception */
    #[Test]
    public function nestedHiddenFileIsNotHandled(): void
    {
        $this->writeFile("{$this->documentRoot()}__PolicyProbeNest/.__policy_probe_secret");
        $disposition = $this->policy->evaluate($this->resolve("/__PolicyProbeNest/.__policy_probe_secret"));
        $this->assertFalse($disposition->shouldHandle);
    }

    /** @throws Exception */
    #[Test]
    public function parentTraversalIsNotHandled(): void
    {
        $disposition = $this->policy->evaluate($this->resolve("/../../etc/passwd"));
        $this->assertFalse($disposition->shouldHandle);
        $this->assertFalse($disposition->cacheable);
    }

    /** @throws Exception */
    #[Test]
    public function visibleFileNextToHiddenSiblingIsHandled(): void
    {
        $this->writeFile("{$this->documentRoot()}__PolicyProbeVisible/app.css");
        $disposition = $this->policy->evaluate($this->resolve("/__PolicyProbeVisible/app.css"));
        $this->assertTrue($disposition->shouldHandle);
    }
}
