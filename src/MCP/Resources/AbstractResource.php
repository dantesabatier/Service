<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Resources;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\FileManager;
use Sabatier\Service\MCP\Response\ResourceContents;
use Sabatier\Service\MCP\Response\ResourceDescriptor;
use Sabatier\Service\MCP\Tools\ToolVocabulary;
use function Sabatier\Foundation\fatal_error;
use function Sabatier\Foundation\string_split_trimmed;
use const Sabatier\Service\MCPAppMimeType;

/**
 * Base class for an MCP resource, the markup a host renders as an App's user interface.
 *
 * Extend this class and place the subclass in the application's `MCPResources` directory to publish it — no
 * registration step, exactly as with a tool.
 *
 * A resource is a template and carries no data. It is handed no `ManagedObjectContext` for that reason: the rows a
 * UI shows arrive through `tools/call`, where `AbstractTool::call()` applies RBAC, ownership and the field-level
 * rules. Markup that embedded rows would answer them to anyone who can read the resource, with none of that applied.
 *
 * @psalm-consistent-constructor
 * @phpstan-consistent-constructor
 */
abstract class AbstractResource
{
    /** @var string The `ui://` identifier a host reads this resource by, and the key it is registered under. */
    abstract public string $uri {
        get;
    }
    /** @var string The name of the markup file in the bundle that owns this concrete resource class. */
    abstract public string $filename {
        get;
    }
    /**
     * @var string Programmatic name; defaults to the last segment of the uri.
     *
     * Derived from the string rather than through `URL`, which rejects the `ui://` scheme as not a locator.
     */
    public string $name {
        get => new ArrayClass(string_split_trimmed($this->uri, "/"))->last ?? $this->uri;
    }
    /** @var string Human-readable display name; defaults to the vocabulary `title` of the name, or the name itself. */
    public string $title {
        get => $this->vocabulary->localize($this->name, "title") ?? $this->name;
    }
    /** @var string|null Explanation of what the resource is for; defaults to the vocabulary `description`, or none. */
    public ?string $description {
        get => $this->vocabulary->localize($this->name, "description");
    }
    /** @var string The media type the resource answers with; an MCP App by default. */
    public string $mimeType {
        get => MCPAppMimeType;
    }
    /** @var array<array-key, mixed>|null The `ui` declaration a host reads before rendering — `csp`, `permissions`, `domain`, `prefersBorder`; null leaves `_meta` off, which a host reads as the defaults. */
    public ?array $ui {
        get => null;
    }
    /** @var ResourceDescriptor The descriptor advertised by `resources/list`. */
    public ResourceDescriptor $descriptor {
        get => new ResourceDescriptor($this->uri, $this->name, $this->mimeType, $this->title, $this->description);
    }

    private ToolVocabulary $vocabulary {
        get => $this->vocabulary ??= ToolVocabulary::forBundle(Bundle::bundleForClass(static::class));
    }

    /**
     * Reads the resource's markup from the bundle that owns its class.
     *
     * @return ResourceContents The contents answered by `resources/read`.
     */
    final public function read(): ResourceContents
    {
        return new ResourceContents($this->uri, $this->mimeType, $this->markup(), $this->ui === null ? null : ["ui" => $this->ui]);
    }

    /**
     * The markup the host renders. A resource whose file is missing from the bundle is a packaging fault, not
     * something a caller can correct, so it fails with the name it looked for.
     */
    protected function markup(): string
    {
        $bundle = Bundle::bundleForClass(static::class);
        $url = $bundle->url($this->filename) ?? fatal_error("\"$this->filename\" is missing from the bundle that owns " . static::class . ".");
        /** @noinspection PhpStrictTypeCheckingInspection */
        return FileManager::default()->contents($url->path) ?: fatal_error("\"$this->filename\" could not be read from the bundle that owns " . static::class . ".");
    }
}
