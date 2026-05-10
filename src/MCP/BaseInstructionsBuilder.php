<?php

declare(strict_types=1);

namespace Sabatier\Service\MCP;

/**
 * Generates the framework-level instructions included in every MCP server response.
 *
 * The app supplies a domain-specific preamble (server description, entity context);
 * this class appends the invariant technical rules so each app does not have to
 * duplicate them.
 */
final class BaseInstructionsBuilder
{
    public function build(?string $appInstructions): string
    {
        $base = $this->baseInstructions();
        if (!$appInstructions || trim($appInstructions) === "") {
            return $base;
        }
        return trim($appInstructions) . "\n\n" . $base;
    }

    private function baseInstructions(): string
    {
        return <<<'INSTRUCTIONS'
        CRITICAL: The number of arguments must equal the number of placeholders exactly — one element per placeholder, in the same order. %K counts as a placeholder: if %K appears twice, the key path must appear twice in arguments.

        Rules:

        0. Make one tool call at a time and wait for the result before making another. Never send multiple tool calls in the same turn.

        1. Call describe_model as the first step of every request, without exception. Attribute names are system-specific and differ from common conventions — never assume or invent them. If describe_model returns a file path instead of inline content, read the relevant sections before continuing.

        2. Only use entity names, attributes, relationships, and enum cases that appear literally in the schema returned by describe_model. Never invent identifiers.

        3. If a tool returns an error, read the message carefully and fix the exact reported issue before retrying. Retry at most once per approach — if the same error occurs again, switch to a completely different strategy (e.g. remove the predicate and filter manually from the results). Never retry more than twice with the same predicate.

        4. For enum-type attributes, check the "cases" map in the schema to find the integer backing value for each case and pass it as an argument.

        5. Match the placeholder to the attribute type:
         - string / mixed / array (for IN and BETWEEN) → %s
         - integer (including objectID and enum backing values) → %d
         - float → %f

         Use dot paths to filter across relationships (e.g. "customer.name"). Examples:
         - "%K = %s", ["status", 1]
         - "%K IN %s", ["status", [0, 1, 2]]
         - "%K BETWEEN %s", ["creationDate", ["2025-01-01", "2025-01-31"]]
         - "%K = %s AND %K = %s", ["isEnabled", true, "area.name", "embroidery"]

         For partial text search always use CONTAINS[cd], never LIKE[cd] with wildcards.

        6. For date attributes, pass ISO 8601 strings (e.g. "2025-01-01").

        7. For sort descriptors, each item must have a non-null, non-empty "key" string and an optional "ascending" boolean (default true). Example: [{"key": "creationDate", "ascending": false}].
        INSTRUCTIONS;
    }
}
