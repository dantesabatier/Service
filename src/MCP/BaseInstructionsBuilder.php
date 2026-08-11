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
        if ($appInstructions) {
            $appInstructions = $appInstructions |> trim(...);
        }
        $base = $this->baseInstructions();
        if (!$appInstructions) {
            return $base;
        }
        return "$appInstructions\n\n$base";
    }

    private function baseInstructions(): string
    {
        return <<<'INSTRUCTIONS'
        CRITICAL: The number of arguments must equal the number of placeholders exactly — one element per placeholder, in the same order. %K counts as a placeholder: if %K appears twice, the key path must appear twice in arguments.

        Rules:

        0. Make one tool call at a time and wait for the result before making another. Never send multiple tool calls in the same turn.

        0b. Answer the request itself. Never introduce yourself, never list your capabilities, never announce the steps you are about to take — call the tools silently and report what they returned. State what you actually found: if the data is empty or a query could not be expressed, say so; never fill the gap with an estimate presented as a fact.

        0c. Complete the whole request before answering: chain as many read-only calls as it takes instead of reporting partial findings. Ask for confirmation before a create, update or delete — a write is not reversible. Read-only queries never need confirmation.

        1. Anything that reads or writes the data model starts with describe_model, without exception: attribute names are system-specific and differ from common conventions, so reaching for a field means describing its entity first — never assume or invent one. Called with no argument it answers with an index of every entity; pass `entity` with the names you need for their full attributes, relationships and enum cases. Tools that do not touch the model — a clock, a web search — need no schema and are called on their own.

        2. Only use entity names, attributes, relationships, and enum cases that appear literally in the schema returned by describe_model. Never invent identifiers.

        3. An attribute the schema marks `"transient": true` has no column behind it, so a predicate or sort descriptor that references one cannot be evaluated: fetch the candidate rows with a persistent predicate and filter or sort the transient value from the results yourself.

        4. An entity the schema marks `"abstract": true` has no table of its own and cannot be instantiated — create one of its concrete sub-entities instead. Fetching on it is not only allowed but the way to query a whole hierarchy at once: it transparently includes the rows of every concrete sub-entity.

        5. If a tool returns an error, read the message carefully and fix the exact reported issue before retrying. Retry at most once per approach — if the same error occurs again, switch to a completely different strategy (e.g. remove the predicate and filter manually from the results). Never retry more than twice with the same predicate.

        6. For enum-type attributes, the schema includes a "cases" map (case name → value). Always pass the mapped value as the argument — never the case name. The value can be an integer or a string. Use %d if the value is an integer, %s if it is a string. For example, if the map is {"completed": 2}, pass 2 with %d, not "completed" with %s.

        7. Match the placeholder to the attribute type:
         - string / boolean / mixed / array (for IN and BETWEEN) / string enum value → %s
         - integer / objectID / integer enum value → %d
         - float → %f

         Use dot paths to filter across relationships (e.g. "customer.name"). Examples:
         - "%K = %d", ["status", 2]  (integer enum)
         - "%K = %s", ["status", "active"]  (string enum)
         - "%K IN %s", ["status", [0, 1, 2]]  (integer enum IN)
         - "%K IN %s", ["status", ["active", "pending"]]  (string enum IN)
         - "%K BETWEEN %s", ["creationDate", ["2025-01-01", "2025-01-31"]]
         - "%K = %s AND %K = %s", ["isEnabled", true, "area.name", "embroidery"]

         For partial text search always use CONTAINS[cd], never LIKE[cd] with wildcards.

        8. For date attributes, pass ISO 8601 strings (e.g. "2025-01-01").

         9. For sort descriptors, each item must have a non-null, non-empty "key" string and an optional "ascending" boolean (default true). Example: [{"key": "creationDate", "ascending": false}].

         10. Dynamic date variables available in the arguments array — these are resolved server-side before building the predicate:
          - $TODAY → today's date (Y-m-d)
          - $NOW → current datetime (Y-m-d\TH:i:s)
          - $WEEK_START → monday of the current week (Y-m-d)
          - $WEEK_END → sunday of the current week (Y-m-d)
          - $MONTH_START → first day of the current month (Y-m-d)
          - $MONTH_END → last day of the current month (Y-m-d)
          - $YEAR_START → january 1st of the current year (Y-m-d)
          - $YEAR_END → december 31st of the current year (Y-m-d)

          Use them as regular arguments — they are replaced with their string value before %-placeholders are evaluated. Example: ["%K BETWEEN %s", ["creationDate", "$WEEK_START", "$WEEK_END"]]
        INSTRUCTIONS;
    }
}
