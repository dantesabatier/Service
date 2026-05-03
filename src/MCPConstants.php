<?php

declare(strict_types=1);

namespace Sabatier\Service;

/** @var string Earliest MCP protocol version supported for backwards compatibility. */
const MCPProtocolVersionLegacy = "2025-03-26";
/** @var string First stable MCP protocol version. */
const MCPProtocolVersionStable = "2025-06-18";
/** @var string Latest stable MCP protocol version. */
const MCPProtocolVersionLatestStable = "2025-11-25";
/** @var string Name of the directory under src/ where the app's MCP tool classes are discovered. */
const MCPToolsDirectory = "MCPTools";
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
/** @var string Environment variable key for the MCP server display name returned during initialization. */
const MCPServerNameKey = "MCP_SERVER_NAME";
/** @var string Default MCP server display name when the environment variable is not set. */
const MCPServerNameDefault = "MCP Server";
/** @var string Environment variable key for the MCP server version returned during initialization. */
const MCPServerVersionKey = "MCP_SERVER_VERSION";
/** @var string Default MCP server version when the environment variable is not set. */
const MCPServerVersionDefault = "1.0.0";
