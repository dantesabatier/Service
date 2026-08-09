# MCP Tools

The framework exposes the application's Core Data model to LLM agents as a JSON-RPC 2.0 tool catalogue at `/mcp`. This document is the per-tool reference: the input schema and behaviour of every built-in tool, and the contract a custom tool must honour.

For the transport, the JSON-RPC methods (`initialize`, `tools/list`, `tools/call`, …) and how the schema is built from the model, see [Section 12 of ARCHITECTURE.md](ARCHITECTURE.md#12-mcp-server).

## Conventions

These hold across every tool:

- **Call `describe_model` first.** Attribute, relationship and enum names are model-specific — never assume them. `describe_model` with no argument returns a lightweight index of every entity; pass `entity` for the full detail of the ones you need.
- **`entity` is a string** naming an entity from the model, and is required by every data tool.
- **Predicates** use the `NSPredicate` format-string syntax: `%K` for a key path, `%@` for a string or object, `%d` for an integer, `%f` for a float. The `arguments` array supplies one value per placeholder, in order — the count must match exactly. Key paths traverse relationships with dot notation (`customer.area.name`). Enum-typed attributes take the integer backing value, never the case name.
- **Enum values** are integers. `describe_model` reports each enum's `cases` map (case name → integer); pass the integer.
- **Every key path and predicate placeholder is validated against the in-memory schema before the database is touched**, so an invalid field name produces a clear message rather than a SQL error.
- **Security is enforced per call.** Because the request URL is always `/mcp`, the URL-driven guards that protect a regular REST endpoint never fire; each tool re-applies the equivalent checks itself (RBAC, ownership scope, resource-level and field-level `#[Readable]`/`#[Writable]`). A tool returns only rows the caller may read and mutates only rows the caller may write; a denied write raises `ForbiddenException`.

---

## `describe_model`

Schema introspection. Should be called first, before any other tool.

| Parameter | Type            | Required | Description |
|-----------|-----------------|----------|-------------|
| `entity`  | array of string | no       | Omit for a lightweight index of every entity. Pass an array of entity names for the full attributes, relationships and enum cases of those entities. |

**With no argument** it returns the index — every entity keyed by name, each reduced to its class, label (`es`), aliases and the *counts* of its attributes and relationships — plus the `predicate_syntax` guide. This always fits in a single tool result, however large the model.

**With `entity`** (one name or a list) it returns the full schema of just those entities: every attribute (type, nullability, enum cases), every relationship (target, cardinality, optionality), plus the `predicate_syntax` guide. An unknown name errors with a hint to call `describe_model` with no argument for the entity list.

`entity` must be a real JSON array of strings — `["Order"]` for one, `["Order", "Customer"]` for several — never a single bracketed string.

---

## `fetch`

Query rows with filtering, sorting, pagination and relationship projection.

| Parameter        | Type    | Required | Description |
|------------------|---------|----------|-------------|
| `entity`         | string  | yes      | Entity name from the data model. |
| `predicate`      | string  | no       | `NSPredicate` format string, e.g. `"%K == %@"`. |
| `arguments`      | array   | no       | Positional arguments for the predicate placeholders, one per placeholder in order. |
| `properties`     | array   | no       | Attribute names to return, e.g. `["objectID", "name", "sku"]`. A JSON array of strings, never a single bracketed string. For nested traversal use `serialization`. |
| `relationships`  | object  | no       | Shallow relationship include, one level deep. Keys are relationship names; values are arrays of attribute names, e.g. `{"customer": ["name", "email"]}`. For nested traversal use `serialization`. |
| `serialization`  | object  | no       | Declarative projection shape traversing relationships to any depth. Map an attribute to `true` to include it, and a relationship to a nested object describing the related entity's shape. `objectID` and the entity name are always included at every level. Example: `{"orderNumber": true, "total": true, "customer": {"name": true, "area": {"name": true}}}`. Takes precedence over `properties` and `relationships`. |
| `sort`           | array   | no       | Sort descriptors, e.g. `[{"key": "creationDate", "ascending": false}]`. `ascending` defaults to `true`. |
| `limit`          | integer | no       | Maximum rows. Omit to return all matching rows. |
| `offset`         | integer | no       | Row offset. |

Returns `{"rowCount": N, "summary": "…", "results": [ … ]}`. Field-level read filtering applies to top-level fields; nested relationship leaves in a serialization shape are not filtered. The `summary` ends with a note that the result is final — it is not a signal to retry.

---

## `count`

Count matching rows.

| Parameter   | Type   | Required | Description |
|-------------|--------|----------|-------------|
| `entity`    | string | yes      | Entity name from the data model. |
| `predicate` | string | no       | `NSPredicate` format string. |
| `arguments` | array  | no       | Positional arguments for the predicate placeholders. |

Returns `{"count": N}`.

---

## `aggregate`

Compute a single aggregate over an attribute across matching rows.

| Parameter   | Type   | Required | Description |
|-------------|--------|----------|-------------|
| `entity`    | string | yes      | Entity name from the data model. |
| `function`  | string | yes      | One of `sum`, `average`, `min`, `max`, `count`, `median`, `mode`, `stddev`. |
| `property`  | string | yes      | Attribute key path to aggregate. |
| `predicate` | string | no       | `NSPredicate` format string scoping the rows. |
| `arguments` | array  | no       | Positional arguments for the predicate placeholders. |

`sum` and `average` require a numeric (`integer`, `float` or `enum`) attribute. `median`, `mode` and `stddev` are computed in memory (the rows are materialized); the others are pushed to the database. The result is rounded to four decimal places. Returns `{"entity": …, "function": …, "property": …, "result": …}`.

`enforceFieldRead` gates the aggregated column, so a protected column is refused even when the caller may read the rows.

---

## `group_by`

`GROUP BY` with per-group aggregates, `HAVING`, sorting and pagination. Fully database-side.

| Parameter          | Type    | Required | Description |
|--------------------|---------|----------|-------------|
| `entity`           | string  | yes      | Entity name from the data model. |
| `group_by`         | array   | yes      | Property key paths to group by. Plain names or dot-notation key paths (`"status"`, `"customer.name"`). No transforms or expressions. |
| `aggregates`       | array   | yes      | Aggregate functions per group. Each item: `{"function": "sum\|average\|min\|max\|count", "property": "keyPath", "as": "resultName"}`. `as` defaults to `"{function}_{property}"`. |
| `predicate`        | string  | no       | `NSPredicate` format string scoping the rows before grouping. |
| `arguments`        | array   | no       | Positional arguments for `predicate`. |
| `having_predicate` | string  | no       | `NSPredicate` format string applied to the grouped rows (`HAVING`). |
| `having_arguments` | array   | no       | Positional arguments for `having_predicate`. |
| `sort`             | array   | no       | Sort descriptors over the grouped result. |
| `limit`            | integer | no       | Maximum rows. Omit to return all. |
| `offset`           | integer | no       | Row offset. |

The `aggregates` function set is narrower than `aggregate`'s — no `median`, `mode` or `stddev`, since those are in-memory only. Every group-by key and aggregate property is gated by `enforceFieldRead`. Returns `{"rowCount": N, "summary": "…", "results": [ … ]}`.

---

## `create`

Insert a single row.

| Parameter | Type   | Required | Description |
|-----------|--------|----------|-------------|
| `entity`  | string | yes      | Concrete entity name from the data model. |
| `values`  | object | yes      | Attribute and relationship values for the new row. A relationship value may be an `objectID` (linking an existing row) or a nested object. |

Rejects an abstract entity — a concrete sub-entity must be named instead. Resource-level `#[Writable]` is enforced *after* the values are populated, so a `where` condition reading the new row's own values sees what is being written. Saves, then returns the created object serialized to the shape of the values that were written, filtered through field-level read.

---

## `update`

Update a single row by `objectID`.

| Parameter  | Type    | Required | Description |
|------------|---------|----------|-------------|
| `entity`   | string  | yes      | Entity name from the data model. |
| `objectID` | integer | yes      | The row's `objectID`. |
| `values`   | object  | yes      | Attribute and relationship values to write. |

The row is located through the security-scoped fetch, so a row the caller may not read is reported as not found rather than disclosed. Ownership and resource-level write access are then enforced. Saves only when the write actually changed something (`hasChanges`). Returns the updated object filtered through field-level read.

---

## `delete`

Delete a single row by `objectID`.

| Parameter  | Type    | Required | Description |
|------------|---------|----------|-------------|
| `entity`   | string  | yes      | Entity name from the data model. |
| `objectID` | integer | yes      | The row's `objectID`. |

Same security-scoped lookup, ownership and resource-level write enforcement as `update`. Deletes and saves. Returns `{"deleted": <objectID>}`.

---

## `persistent_history`

Read or purge the Core Data persistent history change log — the MCP counterpart of the `/history` endpoint. Only meaningful when persistent history tracking is enabled for the store.

| Parameter     | Type    | Required | Description |
|---------------|---------|----------|-------------|
| `operation`   | string  | yes      | `"fetch"` to read history, `"purge"` to permanently delete it. |
| `date`        | string  | no       | ISO 8601 date. fetch: history *after* this date; purge: history *before* it. |
| `transaction` | integer | no       | Transaction-number boundary. fetch: history after it; purge: history before it. |
| `token`       | object  | no       | Persistent history token as a map of store identifier → token number. |
| `entity`      | string  | no       | History entity a `predicate` filters on: `PersistentHistoryTransaction` (default) or `PersistentHistoryChange`. Filter changes from the transaction via the `changes` relationship (`"ANY changes.changeType = %d"`), or filter change rows directly with `PersistentHistoryChange`. Ignored when no predicate is given. |
| `predicate`   | string  | no       | `NSPredicate` format string filtering the history in scope by the chosen entity's properties (`author`, `contextName`, `bundleID`, `changes.changeType`, …). |
| `arguments`   | array   | no       | Positional arguments for the predicate placeholders. |
| `resultType`  | string  | no       | fetch only. Shape of the returned history: `statusOnly`, `objectIDs`, `count`, `transactionsOnly`, `changesOnly`, `transactionsAndChanges` (default). |

The scope parameters `date`, `transaction` and `token` are evaluated in that fixed precedence — only the first one present is used, they are not combined — and a fetch or purge requires exactly one of them. **Purge is destructive** and permanently removes history; it is authorized against the `history` resource with delete rights, whereas fetch requires read rights. Purge returns `{"purged": true}`; fetch returns the history shaped by `resultType`.

---

## `run_job`

Run a named domain job in the current request — the MCP surface over the same job catalogue the CLI entry point (`JobRunner`) matches its command-line argument against. A job is an application `Job` subclass discovered from `src/Jobs/`, not a data-model operation.

| Parameter | Type   | Required | Description |
|-----------|--------|----------|-------------|
| `job`     | string | yes      | Name of the job to run. Matches the job's class short name unless the job overrides its `name` hook. |

The job runs against the request context, and its changes are saved in the same transaction boundary the CRUD tools keep. An unknown name comes back as a correctable failure listing the available jobs; a job that throws propagates its fault out of the registry funnel. Running a job is a coarse action that may read or write, so authorization is enforced per call against the `Jobs` resource with `AuthorizationType::any` — seed a `Jobs` permission of type `any` on the roles allowed to invoke jobs. Returns `{"status": "completed", "job": <name>}`.

---

## `get_server_time`

Returns the server's current date and time. The generic counterpart to the data tools: it answers "what time is it now?" without relying on the timestamp of the prompt, which an agent needs to reason about schedules, deadlines and date-relative predicates.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| *(none)*  |      |          |             |

Returns the server's local time in its configured timezone:

```json
{
  "iso8601": "2026-08-09T14:30:00-05:00",
  "unix": 1754764200,
  "timezone": "America/Mexico_City",
  "weekday": "Sunday",
  "date": "2026-08-09",
  "time": "14:30:00"
}
```

`unix` is the absolute timestamp, so the model can derive any other zone. Reads no entities and enforces no row- or column-level security.

---

## `web_search`

Searches the web — the generic counterpart to the data tools for questions the persisted model cannot answer. Results come back as an `answer` (when the provider returns one) plus a bounded list of `results`.

The tool is provider-agnostic and **built in**: the framework ships the `WebSearchTool` (name, schema, argument validation) in `src/MCP/Tools/` and the provider machinery in `src/Search/` — `WebSearchProvider` (abstract), `TavilySearchProvider`, `WebSearchResult` — and the application picks the backing provider through its environment, the same way it picks which `LLMClient` backs the agent. `WebSearchProvider::provider()` resolves the identifier in `WEB_SEARCH_PROVIDER` (default `tavily`) to a concrete provider and hands it the `WEB_SEARCH_API_KEY` from the same source; no subclassing is required.

| Parameter    | Type    | Required | Description |
|--------------|---------|----------|-------------|
| `query`      | string  | yes      | The web search query, in natural language. |
| `maxResults` | integer | no       | Maximum number of results to return (1–10, default 5). |

The Tavily provider reads its key from the `WEB_SEARCH_API_KEY` environment variable; a provider without its key fails the call with a message telling the model the tool is not configured. Returns:

```json
{
  "query": "...",
  "answer": "...",
  "results": [
    {"title": "...", "url": "...", "content": "..."}
  ]
}
```

`content` is truncated to keep the token cost of the tool result sane. Reads no entities and enforces no row- or column-level security; it is an outbound network call on behalf of the authenticated MCP caller.

---

## Custom Tools

Drop a class extending `AbstractTool` in the application's `src/MCPTools/` directory and the framework discovers it at startup — no registration step. The constructor receives the `ManagedObjectContext` and the `ModelDescriptor`; the subclass supplies three members:

```php
final class MyTool extends AbstractTool
{
    public string $name { get => "my_tool"; }

    public array $inputSchema {
        get => ["type" => "object", "properties" => [ /* … */ ], "required" => [ /* … */ ]];
    }

    public function execute(Dictionary $arguments): ArrayClass
    {
        // … build the result, then wrap it …
        return $this->jsonResult($data);
    }
}
```

`AbstractTool` provides the helpers the built-in tools are built from:

**Building and validating a request**

- `fetchRequest(string $entityName): FetchRequest` — a request bound to the named entity.
- `buildPredicate(string $format, ArrayClass $arguments): Predicate` — a predicate from a format string.
- `validateKeyPath(string $entityName, string $keyPath): void` — reject an unknown attribute or relationship path.
- `validatePredicateKeyPaths(string $entityName, string $format, ArrayClass $arguments): void` — check the placeholder count, the key paths and the enum values in a predicate.
- `assertConcreteEntity(string $entityName): void` — reject an attempt to instantiate an abstract entity.
- `resolveVariables(ArrayClass $params): ArrayClass` — resolve temporal tokens (`$WEEK_START`, …) in predicate arguments to literal dates.

**Wrapping the result**

- `jsonResult(mixed $data): ArrayClass` — a JSON content item.
- `textResult(string $text): ArrayClass` — a plain-text content item.

**Enforcing security — mandatory**

The URL is always `/mcp`, so the endpoint guards never fire. A custom tool that skips these reads or writes rows the caller is not entitled to. Match each to the operation:

| Helper | Call it… |
|--------|----------|
| `applySecurityScope(FetchRequest $request)` | on **every** `FetchRequest` before executing it — folds in the `own` ownership scope and the resource-level `#[Readable]`. |
| `enforceFieldRead(string $entityName, string $keyPath)` | on every key path an aggregate computes over or groups by — a protected column stays protected even over permitted rows. |
| `enforceEntityAuthorization(string $resource, AuthorizationType $action)` | to check per-entity RBAC for the resource and action. |
| `enforceResourceAccess(ManagedObject $object)` | on every object created, updated or deleted — enforces the resource-level `#[Writable]`. On create, call it *after* populating the object. |
| `enforceOwnership(ManagedObject $object)` | on an object being updated or deleted — enforces the `#[Owner]` field. |
| `applySecureRead(ManagedObject $object, Dictionary $data): Dictionary` | to filter a serialized object down to the fields the caller may read. |
| `applySecureUpdate(ManagedObject $object, Dictionary $body)` | to apply a write filtered to the fields the caller may write. |

The rule of thumb: `applySecurityScope` narrows *which rows* a read sees; `enforceFieldRead` / `applySecureRead` narrow *which columns*; the `enforce*` write helpers turn a forbidden mutation into a `ForbiddenException` rather than a silent no-op.
