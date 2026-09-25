<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\UUID;
use Sabatier\Service\NotFoundException;
use Sabatier\Service\Renderer;
use Sabatier\Service\View;
use stdClass;

final class RendererTest extends TestCase
{
    private ?URL $bundleURL = null;

    /** @throws Exception */
    protected function tearDown(): void
    {
        if ($this->bundleURL) {
            FileManager::default()->removeItem($this->bundleURL);
            $this->bundleURL = null;
        }
        parent::tearDown();
    }

    /** @throws Exception */
    #[Test]
    public function aTemplateThatIsNotThereIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->renderer()->render("absent", []);
    }

    /** @throws Exception */
    #[Test]
    public function theRefusalNamesTheTemplateItLookedFor(): void
    {
        try {
            $this->renderer()->render("absent", []);
            $this->fail("A missing template must be refused.");
        } catch (NotFoundException $exception) {
            $this->assertStringContainsString("absent", (string)$exception->error->localizedFailureReason);
        }
    }

    /** @throws Exception */
    #[Test]
    public function aTemplateIsRenderedToItsOutput(): void
    {
        $renderer = $this->renderer(["greeting" => "<p>Hello</p>"]);
        $this->assertSame("<p>Hello</p>", $renderer->render("greeting", []));
    }

    /** @throws Exception */
    #[Test]
    public function theContextIsExtractedIntoTheTemplate(): void
    {
        $renderer = $this->renderer(["profile" => "<?= \$username ?> has <?= \$unread ?>"]);
        $this->assertSame("ada has 3", $renderer->render("profile", ["username" => "ada", "unread" => 3]));
    }

    /** @throws Exception */
    #[Test]
    public function anObjectContextIsExtractedLikeAnArray(): void
    {
        $context = new stdClass();
        $context->username = "grace";
        $renderer = $this->renderer(["profile" => "<?= \$username ?>"]);
        $this->assertSame("grace", $renderer->render("profile", $context));
    }

    /** @throws Exception */
    #[Test]
    public function aTemplateMayIncludeAnother(): void
    {
        $renderer = $this->renderer(["page" => "head <?= \$include_view(\"partial\") ?> tail", "partial" => "MIDDLE"]);
        $this->assertSame("head MIDDLE tail", $renderer->render("page", []));
    }

    /** @throws Exception */
    #[Test]
    public function anIncludedTemplateReceivesItsOwnContext(): void
    {
        $renderer = $this->renderer(["page" => "<?= \$include_view(\"partial\", [\"name\" => \"ada\"]) ?>", "partial" => "hi <?= \$name ?>"]);
        $this->assertSame("hi ada", $renderer->render("page", []));
    }

    /** @throws Exception */
    #[Test]
    public function anIncludedTemplateDoesNotInheritTheOuterContext(): void
    {
        // extract() is given only the sub-context, so an outer variable is not visible inside.
        $renderer = $this->renderer(["page" => "<?= \$include_view(\"partial\") ?>", "partial" => "<?= isset(\$secret) ? \"leaked\" : \"clean\" ?>"]);
        $this->assertSame("clean", $renderer->render("page", ["secret" => "shh"]));
    }

    /** @throws Exception */
    #[Test]
    public function theTemplateCanReachTheIncludeHelperItself(): void
    {
        $renderer = $this->renderer(["page" => "<?= is_callable(\$include_view) ? \"callable\" : \"no\" ?>"]);
        $this->assertSame("callable", $renderer->render("page", []));
    }

    /** @throws Exception */
    #[Test]
    public function aViewRendersThroughItsOwnRenderer(): void
    {
        $renderer = $this->renderer(["card" => "card for <?= \$name ?>"]);
        $this->assertSame("card for ada", new View("card", ["name" => "ada"], $renderer)->render());
    }

    /**
     * @param array<string, string> $templates
     * @throws Exception
     */
    private function renderer(array $templates = []): Renderer
    {
        $this->bundleURL = FileManager::default()->temporaryDirectory->appendingPathComponent(new UUID()->uuidString);
        FileManager::default()->createDirectory($this->bundleURL, true);
        foreach ($templates as $name => $contents) {
            FileManager::default()->createFile($this->bundleURL->appendingPathComponent("$name.php")->path, $contents);
        }
        return new Renderer(Bundle::bundleWithURL($this->bundleURL));
    }
}
