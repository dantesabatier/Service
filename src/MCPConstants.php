<?php

declare(strict_types=1);

/**
 * MCP protocol constants and defaults.
 *
 * These constants configure the default behavior and filenames used by the
 * MCP subsystem and are part of the public MCP protocol surface.
 */
namespace Sabatier\Service;

/** @var string Earliest MCP protocol version supported for backwards compatibility. */
const MCPProtocolVersionLegacy = "2025-03-26";
/** @var string First stable MCP protocol version. */
const MCPProtocolVersionStable = "2025-06-18";
/** @var string Latest stable MCP protocol version. */
const MCPProtocolVersionLatestStable = "2025-11-25";
/** @var string Name of the directory under src/ where the app's MCP tool classes are discovered. */
const MCPToolsDirectory = "MCPTools";
/** @var string Response and request header carrying the Streamable HTTP session identifier. */
const MCPSessionHeader = "Mcp-Session-Id";
/** @var string Request header carrying the protocol version negotiated during initialization. */
const MCPProtocolVersionHeader = "MCP-Protocol-Version";
/** @var string Environment variable key for the comma-separated list of browser origins allowed to reach the MCP endpoint. Requests without an `Origin` header are unaffected; MCP clients are not browsers and do not send one. */
const MCPAllowedOriginsKey = "MCP_ALLOWED_ORIGINS";
/** @var string Environment variable key for the idle lifetime of an MCP session, in seconds. */
const MCPSessionTTLKey = "MCP_SESSION_TTL";
/** @var int Default idle lifetime of an MCP session, in seconds, when the environment variable is not set. The TTL is refreshed on every request that carries the session, so only an abandoned session expires. */
const MCPSessionTTLDefault = 3600;
/** @var string Environment variable key for the audience claim a token must carry to reach the MCP endpoint. Leave unset to accept any audience. */
const MCPTokenAudienceKey = "MCP_TOKEN_AUDIENCE";
/** @var string Environment variable key for the bundle resource filename of the domain vocabulary used to localize the model schema. */
const MCPVocabularyFilenameKey = "MCP_VOCABULARY_FILENAME";
/** @var string Default bundle resource filename for the domain vocabulary when the environment variable is not set. */
const MCPVocabularyFilenameDefault = "mcp_vocabulary.json";
/** @var string Environment variable key for the bundle resource filename of the predicate examples included in the model schema guide. */
const MCPPredicateExamplesFilenameKey = "MCP_PREDICATE_EXAMPLES_FILENAME";
/** @var string Default bundle resource filename for the predicate examples when the environment variable is not set. */
const MCPPredicateExamplesFilenameDefault = "mcp_predicate_examples.json";
/** @var string Environment variable key for the bundle resource filename containing the MCP server instructions. */
const MCPInstructionsFilenameKey = "MCP_INSTRUCTIONS_FILENAME";
/** @var string Default bundle resource filename for the MCP server instructions when the environment variable is not set. */
const MCPInstructionsFilenameDefault = "mcp_instructions.txt";
/** @var string Conventional bundle resource filename for a chat agent's domain instructions. Pass it to `MCPInstructionsProvider`. */
const ChatInstructionsFilenameDefault = "chat_system_prompt.txt";
/** @var string Conventional bundle resource filename for a widget agent's domain instructions. Pass it to `MCPInstructionsProvider`. */
const WidgetInstructionsFilenameDefault = "widget_system_prompt.txt";
/** @var string Environment variable key for the MCP server display name returned during initialization. */
const MCPServerNameKey = "MCP_SERVER_NAME";
/** @var string Default MCP server display name when the environment variable is not set. */
const MCPServerNameDefault = "MCP Server";
/** @var string Environment variable key for the MCP server version returned during initialization. */
const MCPServerVersionKey = "MCP_SERVER_VERSION";
/** @var string Default MCP server version when the environment variable is not set. */
const MCPServerVersionDefault = "1.0.0";
/** @var string Environment variable key for the Tavily API key consumed by `Sabatier\Service\Search\TavilySearchProvider`; the application decides how it reads it. */
const WebSearchApiKey = "WEB_SEARCH_API_KEY";
/** @var string Environment variable key for the identifier of the `WebSearchProvider` the server uses to back `web_search`. */
const WebSearchProviderKey = "WEB_SEARCH_PROVIDER";
/** @var string Default `WebSearchProvider` identifier when the environment variable is not set. */
const WebSearchProviderDefault = "tavily";
