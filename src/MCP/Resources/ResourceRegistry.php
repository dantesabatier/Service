<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP\Resources;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Service\MCP\Response\ResourceDescriptor;

/**
 * Holds the resolved MCP resources and answers reads by uri.
 *
 * A caller's uri is only ever matched against the keys this registry holds; it never reaches the filesystem, so a uri
 * naming anything other than a published resource resolves to nothing rather than to a file.
 */
final class ResourceRegistry
{
    /** @var Dictionary<AbstractResource> */
    private Dictionary $resources {
        get => $this->resources ??= $this->resourceList->reduce(new Dictionary(),
            /**
             * @param Dictionary<AbstractResource> $carry
             * @return Dictionary<AbstractResource>
             */
            function (Dictionary $carry, AbstractResource $resource): Dictionary {
                $carry[$resource->uri] = $resource;
                return $carry;
            });
    }
    /** @var ArrayClass<ResourceDescriptor> Descriptors advertised to the host, in registry order. */
    public ArrayClass $list {
        get => $this->list ??= $this->resources->map(fn(AbstractResource $resource): ResourceDescriptor => $resource->descriptor);
    }

    /** @param ArrayClass<AbstractResource> $resourceList The resolved resources to register by uri. */
    public function __construct(private readonly ArrayClass $resourceList)
    {
    }

    /**
     * Returns the resource published under the given uri, or null when none is.
     *
     * @param string $uri The uri the caller asked to read.
     */
    public function resource(string $uri): ?AbstractResource
    {
        return $this->resources[$uri];
    }
}
