<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Service\MCP\InitializeHandler;
use Sabatier\Service\MCP\JSONRPCRequestParser;
use Sabatier\Service\MCP\MCPRequestHandler;
use Sabatier\Service\MCP\MethodDispatcher;
use Sabatier\Service\MCP\Schema\AttributeSchemaFactory;
use Sabatier\Service\MCP\Schema\ModelDescriptor;
use Sabatier\Service\MCP\Schema\ModelSchemaExtractor;
use Sabatier\Service\MCP\Schema\PredicateGuideFactory;
use Sabatier\Service\MCP\Schema\SchemaLocalizer;
use Sabatier\Service\MCP\Schema\VocabularyRepository;
use Sabatier\Service\MCP\ToolResolver;
use Sabatier\Service\MCP\Tools\ToolRegistry;
use Sabatier\Service\MCP\ToolsCallHandler;
use Sabatier\Service\MCP\ToolsListHandler;

#[Endpoint("/mcp", [JSONRPCTransformer::class, JSONTransformer::class])]
final class MCPResponder extends Responder
{
    /** @var ArrayClass<string> */
    #[Override]
    protected ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::get, HTTPRequestMethod::post]);
    }
    private ModelDescriptor $descriptor {
        get => $this->descriptor ??= new ModelDescriptor(new ModelSchemaExtractor($this->managedObjectContext, new AttributeSchemaFactory()), new VocabularyRepository(), new SchemaLocalizer(), new PredicateGuideFactory());
    }
    private ToolRegistry $registry {
        get => $this->registry ??= new ToolRegistry(new ToolResolver($this->managedObjectContext, $this->descriptor)->resolve());
    }
    #[Override]
    protected mixed $data {
        get => $this->data ??= new MCPRequestHandler(new JSONRPCRequestParser(), new MethodDispatcher(new InitializeHandler(), new ToolsListHandler($this->registry), new ToolsCallHandler($this->registry)))->handle($this->request);
    }
}
