# MCP Tools

The framework exposes the generated application's Core Data model to LLM agents as a JSON-RPC 2.0 tool catalogue at `/mcp`. The built-in data tools are derived from that model without programmer-written tool definitions. This document is the per-tool reference and the contract for optional custom tools.

For the transport, the JSON-RPC methods (`initialize`, `tools/list`, `tools/call`, `resources/list`, `resources/read`, …) and how the schema is built from the model, see [Section 12 of ARCHITECTURE.md](ARCHITECTURE.md#12-mcp-server).

## Protocol compatibility

This implementation serves the stateful, handshake-era MCP revisions `2025-03-26`, `2025-06-18` and `2025-11-25`. It negotiates through `initialize`, issues an `Mcp-Session-Id`, and defaults or falls back to `2025-11-25` when the client does not request one of those revisions.

It does not yet implement the stateless `2026-07-28` lifecycle: there is no `server/discover`, per-request capability metadata, or `Mcp-Method`/`Mcp-Name` routing. A client that supports both eras must select its legacy or handshake mode, or explicitly request one of the supported revisions. See the [official 2026-07-28 release notes](https://blog.modelcontextprotocol.io/posts/2026-07-28/) for the lifecycle differences.

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

| Parameter | Type            | Required | Description                                                                                                                                          |
|-----------|-----------------|----------|------------------------------------------------------------------------------------------------------------------------------------------------------|
| `entity`  | array of string | no       | Omit for a lightweight index of every entity. Pass an array of entity names for the full attributes, relationships and enum cases of those entities. |

**With no argument** it returns the index — every entity keyed by name under `entities`, each reduced to its class, label (`es`), aliases and the *counts* of its attributes and relationships — plus the `predicate_syntax` guide and a `usage` line naming how to ask for detail. This always fits in a single tool result, however large the model.

**With `entity`** (one name or a list) it returns the full schema of just those entities: every attribute (type, nullability, enum cases), every relationship (target, cardinality, optionality, delete rule), plus the `predicate_syntax` guide. An unknown name errors with a hint to call `describe_model` with no argument for the entity list.

Pass `entity` as a real JSON array of strings — `["Order"]` for one, `["Order", "Customer"]` for several. Never send a *string that looks like* an array (`"[\"Order\"]"`): it is read as one entity name, and no entity is called that. A bare string (`"Order"`) is tolerated and treated as a one-element list, but the schema declares an array, so a strictly validating client will reject it — prefer the array form.

---

## `fetch`

Query rows with filtering, sorting, pagination and relationship projection.

| Parameter       | Type    | Required | Description                                                                                                                                                                                                                                                                                                                                                                                                                |
|-----------------|---------|----------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `entity`        | string  | yes      | Entity name from the data model.                                                                                                                                                                                                                                                                                                                                                                                           |
| `predicate`     | string  | no       | `NSPredicate` format string, e.g. `"%K == %@"`.                                                                                                                                                                                                                                                                                                                                                                            |
| `arguments`     | array   | no       | Positional arguments for the predicate placeholders, one per placeholder in order.                                                                                                                                                                                                                                                                                                                                         |
| `properties`    | array   | no       | Attribute names to return, e.g. `["objectID", "name", "sku"]`. A JSON array of strings, never a single bracketed string. For nested traversal use `serialization`.                                                                                                                                                                                                                                                         |
| `relationships` | object  | no       | Shallow relationship include, one level deep. Keys are relationship names; values are arrays of attribute names, e.g. `{"customer": ["name", "email"]}`. For nested traversal use `serialization`.                                                                                                                                                                                                                         |
| `serialization` | object  | no       | Declarative projection shape traversing relationships to any depth. Map an attribute to `true` to include it, and a relationship to a nested object describing the related entity's shape. `objectID` and the entity name are always included at every level. Example: `{"orderNumber": true, "total": true, "customer": {"name": true, "area": {"name": true}}}`. Takes precedence over `properties` and `relationships`. |
| `sort`          | array   | no       | Sort descriptors, e.g. `[{"key": "creationDate", "ascending": false}]`. `ascending` defaults to `true`.                                                                                                                                                                                                                                                                                                                    |
| `limit`         | integer | no       | Maximum rows. Omit to return all matching rows.                                                                                                                                                                                                                                                                                                                                                                            |
| `offset`        | integer | no       | Row offset.                                                                                                                                                                                                                                                                                                                                                                                                                |

Returns `{"rowCount": N, "summary": "…", "results": [ … ]}`. Field-level read filtering applies to top-level fields; nested relationship leaves in a serialization shape are not filtered by `#[Readable]`, though an attribute marked sensitive in the model never serialises its value at any depth. The `summary` ends with a note that the result is final — it is not a signal to retry.

---

## `count`

Count matching rows.

| Parameter   | Type   | Required | Description                                                                                                                 |
|-------------|--------|----------|-----------------------------------------------------------------------------------------------------------------------------|
| `entity`    | string | yes      | Entity name from the data model.                                                                                            |
| `predicate` | string | no       | `NSPredicate` format string.                                                                                                |
| `arguments` | array  | no       | Positional arguments for the predicate placeholders. Temporal tokens (`$TODAY`, `$WEEK_START`, …) are resolved server-side. |

Returns `{"count": N}`.

---

## `aggregate`

Compute a single aggregate over an attribute across matching rows.

| Parameter   | Type   | Required | Description                                                                                                                 |
|-------------|--------|----------|-----------------------------------------------------------------------------------------------------------------------------|
| `entity`    | string | yes      | Entity name from the data model.                                                                                            |
| `function`  | string | yes      | One of `sum`, `average`, `min`, `max`, `count`, `median`, `mode`, `stddev`.                                                 |
| `property`  | string | yes      | Attribute key path to aggregate.                                                                                            |
| `predicate` | string | no       | `NSPredicate` format string scoping the rows.                                                                               |
| `arguments` | array  | no       | Positional arguments for the predicate placeholders. Temporal tokens (`$TODAY`, `$WEEK_START`, …) are resolved server-side. |

`sum` and `average` require a numeric (`integer`, `float` or `enum`) attribute — a check that only applies to an attribute on the named entity, so a dotted key path (`customer.total`) reaches the database unchecked. `median`, `mode` and `stddev` are computed in memory (the rows are materialized); the others are pushed to the database. The result is rounded to four decimal places. Returns `{"entity": …, "function": …, "property": …, "result": …}`.

`enforceFieldRead` gates the aggregated column, so a protected column is refused even when the caller may read the rows.

---

## `group_by`

`GROUP BY` with per-group aggregates, `HAVING`, sorting and pagination. Fully database-side.

| Parameter          | Type    | Required | Description                                                                                                                                                                       |
|--------------------|---------|----------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `entity`           | string  | yes      | Entity name from the data model.                                                                                                                                                  |
| `group_by`         | array   | yes      | Property key paths to group by. Plain names or dot-notation key paths (`"status"`, `"customer.name"`). No transforms or expressions.                                              |
| `aggregates`       | array   | yes      | Aggregate functions per group. Each item: `{"function": "sum\|average\|min\|max\|count", "property": "keyPath", "as": "resultName"}`. `as` defaults to `"{function}_{property}"`. |
| `predicate`        | string  | no       | `NSPredicate` format string scoping the rows before grouping.                                                                                                                     |
| `arguments`        | array   | no       | Positional arguments for `predicate`.                                                                                                                                             |
| `having_predicate` | string  | no       | `NSPredicate` format string applied to the grouped rows (`HAVING`).                                                                                                               |
| `having_arguments` | array   | no       | Positional arguments for `having_predicate`.                                                                                                                                      |
| `sort`             | array   | no       | Sort descriptors over the grouped result.                                                                                                                                         |
| `limit`            | integer | no       | Maximum rows. Omit to return all.                                                                                                                                                 |
| `offset`           | integer | no       | Row offset.                                                                                                                                                                       |

The `aggregates` function set is narrower than `aggregate`'s — no `median`, `mode` or `stddev`, since those are in-memory only. Every group-by key and aggregate property is gated by `enforceFieldRead`. Returns `{"rowCount": N, "summary": "…", "results": [ … ]}`.

---

## `create`

Insert a single row.

| Parameter | Type   | Required | Description                                                                                                                                |
|-----------|--------|----------|--------------------------------------------------------------------------------------------------------------------------------------------|
| `entity`  | string | yes      | Concrete entity name from the data model.                                                                                                  |
| `values`  | object | yes      | Attribute and relationship values for the new row. A relationship value may be an `objectID` (linking an existing row) or a nested object. |

Rejects an abstract entity — a concrete sub-entity must be named instead. Resource-level `#[Writable]` is enforced *after* the values are populated, so a `where` condition reading the new row's own values sees what is being written. Saves, then returns the created object serialized to the shape of the values that were written, filtered through field-level read.

---

## `update`

Update a single row by `objectID`.

| Parameter  | Type    | Required | Description                                 |
|------------|---------|----------|---------------------------------------------|
| `entity`   | string  | yes      | Entity name from the data model.            |
| `objectID` | integer | yes      | The row's `objectID`.                       |
| `values`   | object  | yes      | Attribute and relationship values to write. |

`values` must include at least one of the row's own attributes, even at its current value. Values that carry only relationships write the related rows but leave the row itself unmodified: it never reaches the context's updated objects, so its `willSave()` does not run and the attributes it derives from those relationships keep their previous values.

The row is located through the security-scoped fetch, so a row the caller may not read is reported as not found rather than disclosed. Ownership and resource-level write access are then enforced. Saves only when the write actually changed something (`hasChanges`). Returns the updated object filtered through field-level read.

---

## `delete`

Delete a single row by `objectID`.

| Parameter  | Type    | Required | Description                      |
|------------|---------|----------|----------------------------------|
| `entity`   | string  | yes      | Entity name from the data model. |
| `objectID` | integer | yes      | The row's `objectID`.            |

Same security-scoped lookup, ownership and resource-level write enforcement as `update`. Deletes and saves. Returns `{"deleted": <objectID>}`.

The related rows follow the `deleteRule` that `describe_model` reports on each relationship: `cascade` deletes them in the same save, so a client deletes only the parent; `nullify` unlinks them; `deny` refuses the deletion while any remain; `noAction` does nothing to them, leaving the outcome to the store's foreign key. The tool description tells the agent to read the rules before deleting rather than deleting children one by one.

---

## `persistent_history`

Read or purge the Core Data persistent history change log — the MCP counterpart of the `/history` endpoint. Only meaningful when persistent history tracking is enabled for the store.

| Parameter     | Type    | Required | Description                                                                                                                                                                                                                                                                                                              |
|---------------|---------|----------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `operation`   | string  | yes      | `"fetch"` to read history, `"purge"` to permanently delete it.                                                                                                                                                                                                                                                           |
| `date`        | string  | no       | ISO 8601 date. fetch: history *after* this date; purge: history *before* it.                                                                                                                                                                                                                                             |
| `transaction` | integer | no       | Transaction-number boundary. fetch: history after it; purge: history before it.                                                                                                                                                                                                                                          |
| `token`       | object  | no       | Persistent history token as a map of store identifier → token number.                                                                                                                                                                                                                                                    |
| `entity`      | string  | no       | History entity a `predicate` filters on: `PersistentHistoryTransaction` (default) or `PersistentHistoryChange`. Filter changes from the transaction via the `changes` relationship (`"ANY changes.changeType = %d"`), or filter change rows directly with `PersistentHistoryChange`. Ignored when no predicate is given. |
| `predicate`   | string  | no       | `NSPredicate` format string filtering the history in scope by the chosen entity's properties (`author`, `contextName`, `bundleID`, `changes.changeType`, …).                                                                                                                                                             |
| `arguments`   | array   | no       | Positional arguments for the predicate placeholders.                                                                                                                                                                                                                                                                     |
| `resultType`  | string  | no       | fetch only. Shape of the returned history: `statusOnly`, `objectIDs`, `count`, `transactionsOnly`, `changesOnly`, `transactionsAndChanges` (default).                                                                                                                                                                    |

The scope parameters `date`, `transaction` and `token` are evaluated in that fixed precedence — only the first one present is used, they are not combined — and a fetch or purge requires at least one of them, failing with a message naming the three when none is given. Passing more than one is not an error: the extras are silently ignored, so send exactly the one you mean. **Purge is destructive** and permanently removes history; it is authorized against the `history` resource with delete rights, whereas fetch requires read rights. Purge returns `{"purged": true}`; fetch returns the history shaped by `resultType`.

---

## `run_job`

Run a named domain job in the current request — the MCP surface over the same job catalogue the CLI entry point (`JobRunner`) matches its command-line argument against. A job is an application `Job` subclass discovered from `src/Jobs/`, not a data-model operation.

| Parameter | Type   | Required | Description                                                                                          |
|-----------|--------|----------|------------------------------------------------------------------------------------------------------|
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
  "unix": 1786303800,
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

The tool is provider-agnostic and **built in**: the framework ships the `WebSearchTool` (name, schema, argument validation) in `src/MCP/Tools/` and the provider machinery in `src/Search/` — `WebSearchProvider` (abstract), `TavilySearchProvider`, `WebSearchResult` — and the application picks the backing provider through its environment. `WebSearchProvider::provider()` resolves the identifier in `WEB_SEARCH_PROVIDER` (default `tavily`) to a concrete provider and hands it the `WEB_SEARCH_API_KEY` from the same source; no subclassing is required. This is the only environment-driven provider selection in the framework — the `LLMClient` backing an agent is constructed in code and injected into `LLMAgent`, with its model, endpoint and key as constructor arguments.

| Parameter    | Type    | Required | Description                                            |
|--------------|---------|----------|--------------------------------------------------------|
| `query`      | string  | yes      | The web search query, in natural language.             |
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

## Tool annotations

`tools/list` includes advisory `annotations` built by `ToolRegistry` from each `AbstractTool`:

| Property        | MCP field         | Default |
|-----------------|-------------------|---------|
| `isReadOnly`    | `readOnlyHint`    | `false` |
| `isDestructive` | `destructiveHint` | `true`  |
| `isIdempotent`  | `idempotentHint`  | `false` |
| `isOpenWorld`   | `openWorldHint`   | `true`  |

Override these property hooks when the implementation can guarantee a narrower effect. `isReadOnly` describes **every** operation the tool exposes: `persistent_history` advertises `false` because it can purge, even though `isReadOnlyCall()` permits individual fetch calls as reads.

Destructive includes overwriting, not just deleting. `create` can update existing objects inside a submitted graph, so it keeps the conservative destructive default. Idempotence concerns repeated effects, not identical results or cacheability; neither `isIdempotent` nor remote hints enable retries or result caching. The destructive and idempotent hints are meaningful for writing tools; their defaults do not make a read-only tool destructive.

Built-in data tools and the server clock declare a closed domain (`isOpenWorld = false`). Web search and arbitrary app jobs retain the open-world default. Custom tools inherit the conservative defaults, so existing apps remain compatible; a tool limited to its application's data can override `isOpenWorld` to `false`.

`ToolDescriptor::$annotations` is optional for manually supplied catalogues. Missing annotations stay absent; explicit `false` values and an empty object survive serialization. `MCPClient` retains remote hints and rejects malformed known fields without coercing strings to booleans. Provider formatters send only their tool schema fields, not MCP annotations.

These are [MCP hints, not authorization](https://modelcontextprotocol.io/specification/2025-11-25/schema#toolannotations). `MCPToolExecutor` still requires application-supplied trusted classifiers for read approval and caching. Local authorization and the agent's write-approval policy remain unchanged.

## Custom Tools

Drop a class extending `AbstractTool` in the application's `src/MCPTools/` directory and the framework discovers it at startup — no registration step. The constructor receives the `ManagedObjectContext` and the `ModelDescriptor`; the subclass supplies three members:

```php
final class MyTool extends AbstractTool
{
    public string $name { get => "my_tool"; }

    public array $inputSchema {
        get => ["type" => "object", "properties" => [ /* … */ ], "required" => [ /* … */ ]];
    }

    protected function executeCore(Dictionary $arguments): ArrayClass
    {
        // … build the result, then wrap it …
        return $this->jsonResult($data);
    }
}
```

`ToolRegistry` invokes a tool only through the final `call()`, which authorizes the call and then runs `executeCore()`, the one abstract method. The context is private to `AbstractTool`: a tool reads and writes through the helpers below.

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

| Helper                                                                                                                                                   | Call it…                                                                                                                                                                                                                                                                  |
|----------------------------------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `fetch(FetchRequest $request)` / `count(FetchRequest $request)` / `fetchObjects(FetchRequest $request)`                                                  | to execute **every** `FetchRequest` — they fold in the `own` ownership scope and the resource-level `#[Readable]`, and `fetch()` filters each object to the fields the caller may read. `fetchObjects()` returns unserialized objects for a tool that computes over them. |
| `enforceFieldRead(string $entityName, string $keyPath)`                                                                                                  | on every key path an aggregate computes over or groups by — a protected column stays protected even over permitted rows.                                                                                                                                                  |
| `modify(string $entityName, $objectID, Closure $modification)`                                                                                           | to write through a domain change rather than a set of values — the same lookup and rules as `update()`, applied to the row named; the change runs, the context is saved and the stored row is answered.                                                                   |
| `executeHistory(PersistentHistoryChangeRequest $changeRequest, ?FetchRequest $transactionFilter)` / `runJob(Job $job)`                                   | to run a persistent history request or a job through the same operations `/history` and the CLI use.                                                                                                                                                                      |
| `tools(): Dictionary`                                                                                                                                    | the tool catalogue, keyed by name — to validate what another tool will be asked to do.                                                                                                                                                                                    |
| `entityDescription(string $entityName): ?EntityDescription`                                                                                              | an entity of the model, or null when there is none.                                                                                                                                                                                                                       |
| `authorizationResource(Dictionary $arguments): ?string` / `authorizationAction(Dictionary $arguments): AuthorizationType`                                | to override the resource and action `call()` authorizes before `executeCore()` — the `entity` argument and `read` (or `update` when the call is not read-only) by default. Return a null resource for a call that needs no authorization.                                 |
| `create(string $entityName, Dictionary $values)` / `update(string $entityName, $objectID, Dictionary $values)` / `delete(string $entityName, $objectID)` | to write — they look the row up within the read scope, enforce `#[Owner]` for their own action and the resource-level `#[Writable]`, filter the values by the field-level `#[Writable]`, and answer the stored row filtered to the fields the caller may read.            |
| `enforceEntityAuthorization(string $resource, AuthorizationType $action)`                                                                                | for a tool that reads several entities: on each entity beyond the one `call()` authorized.                                                                                                                                                                                |

The rule of thumb: the read helpers narrow *which rows* a read sees; `fetch()` and `enforceFieldRead` narrow *which columns*; the write helpers turn a forbidden mutation into a `ForbiddenException` rather than a silent no-op.

## Structured results

A tool's answer travels twice: `content` is the text the model reads, and `structuredContent` is the same answer as data, for a client that renders it. Every tool answering through `jsonResult()` contributes it already — the payload passed there is kept as the call's structured content while it is serialized, so no tool needed changing.

A tool whose text is prose rather than the serialization of its data contributes one explicitly:

```php
return $this->structuredResult($this->textResult("Widget \"$title\" saved."), ["objectID" => $identifier]);
```

Two rules the framework applies on its own:

- A payload that serializes to a JSON **array** is left out. `structuredContent` is defined as an object. A tool answering with a list wraps it in one — `{"rowCount": 12, "results": [...]}`, which is what `fetch` and `group_by` already do.
- The field is omitted entirely for a session that negotiated `2025-03-26`, which does not define it. A tool never has to ask which revision it is answering.

The payload is taken from the serialization, not from the argument, so a `Dictionary`, an `ArrayClass` or a managed object all answer with the shape the client is sent — a tool does not have to flatten anything by hand.

**The text still has to stand on its own.** A client that renders no UI receives `content` and nothing else, so `structuredContent` is an addition for a UI, never a substitute for an answer the model can read. A tool that moves its real answer into the payload and leaves `content` a stub degrades to saying nothing on every client that does not render it.

## MCP Apps (resources)

An App is a user interface a host renders beside the conversation. It is published as a **resource**: markup with a `ui://` uri and the `text/html;profile=mcp-app` media type, which the host reads with `resources/read`. The framework serves `resources/list` and `resources/read`, and advertises the `resources` capability during the handshake.

Drop a class extending `AbstractResource` in the application's `src/MCPResources/` directory and the framework discovers it — no registration step, exactly as with a tool:

```php
final class DashboardResource extends AbstractResource
{
    #[Override]
    public string $uri { get => "ui://dashboard/main"; }

    #[Override]
    public string $filename { get => "dashboard.html"; }

    #[Override]
    public ?array $ui {
        get => ["csp" => ["connectDomains" => [], "resourceDomains" => []], "prefersBorder" => true];
    }
}
```

`dashboard.html` sits in the application's `Resources/` directory beside the vocabulary files, and the class takes no constructor arguments — `ResourceResolver` instantiates it with `new $class()`.

Only `uri` and `filename` are abstract; everything else has a default.

- **`uri`** — the identifier the host reads by, and the key the registry holds. A caller's uri is only ever matched against those keys, so it never reaches the filesystem and cannot be steered at one. The scheme is `ui://` by convention; the framework treats the whole string as opaque.
- **`filename`** — the markup in the bundle that owns the class, resolved through `Bundle::url()` and localizable like any other bundle resource. A single-file page with its script inline, since a host fetches nothing else on the page's behalf. A filename missing from the bundle fails loudly, naming the file and the class: it is a packaging fault, not something a caller can correct by asking differently.
- **`ui`** — the `_meta.ui` declaration the host reads before rendering: `csp` (`connectDomains`, `resourceDomains`, `frameDomains`, `baseUriDomains`), `permissions`, `domain`, `prefersBorder`. Return `null` to leave `_meta` off entirely, which a host reads as its defaults.
- **`name`** — the uri's last segment (`main`, above).
- **`title`** and **`description`** — looked up in the bundle's `mcp_vocabulary.json` under the resource's `name`, through the same `ToolVocabulary` that localizes a tool's title, and therefore under that file's **`"tools"`** section — the mechanism is shared, so the section name is the tools one even for a resource. An entry that is absent leaves the title as the name and the description empty; override either member to fix it in code instead.
- **`mimeType`** — `text/html;profile=mcp-app`. Override only to publish something that is not an App.

Tie a tool to the App that renders its answer with `uiResourceUri`, which `tools/list` emits as `_meta.ui.resourceUri`:

```php
public ?string $uiResourceUri { get => "ui://dashboard/main"; }
```

That is a declaration for the host, not a restriction the server enforces: the tool stays callable by a model with no UI in sight, and keeps answering text as it always did.

The specification requires the uri to name a resource that exists on this server, but the framework does not check it — a typo surfaces as a host that renders nothing. Read the value back from `resources/list` after wiring one up, and keep the uri in one place when a tool and its resource are written together.

### What the page has to do to be rendered at all

The page and the host speak JSON-RPC 2.0 over `postMessage` — no SDK required, though `@modelcontextprotocol/sdk` saves hand-rolling it. Three things are **mandatory**, and a page missing any of them is read as text while every server-side probe still looks correct. This is the part that costs an afternoon:

1. **`ui/initialize` carries real parameters.** `appInfo` (a `name` and `version` of the page's own), `appCapabilities`, and `protocolVersion` — the extension's revision, `"2026-01-26"`, which is **not** the one the server negotiated on `/mcp`. Sending `{}` leaves the host nothing to negotiate.
2. **`ui/notifications/initialized`** follows the initialize result.
3. **`ui/notifications/size-changed`** reports `{width, height}`. An iframe cannot size itself: without this the host gives the view no room, so a page that renders perfectly still shows nothing. Send it once after initializing and again whenever the content reflows — a `ResizeObserver` on the root, with the last reported size remembered so an unchanged size sends nothing.

The host then pushes the data: `ui/notifications/tool-result`, whose `params` is the whole `CallToolResult` — `content`, `structuredContent` and `_meta`. There is no way to ask for it; it arrives when the tool finishes.

### How the data reaches the UI

The markup is served once and holds no rows. The page asks for them the way any other client does — a `tools/call` the host forwards on its behalf — and reads the answer from `structuredContent`:

1. The host reads the resource and renders the markup in a sandboxed iframe.
2. The page sends a `tools/call` to the host over the same bridge.
3. The host forwards it to `/mcp` as an ordinary `tools/call`, carrying the connection's credentials.
4. `AbstractTool::call()` authorizes it and the tool answers; the page reads `structuredContent`, the model reads `content`.

Step 3 is the whole security argument: the UI is a caller like any other, and gets exactly what that caller is entitled to. A tool reached from the UI needs no special casing — and must not be given any.

### Debugging a view that will not render

Every check below can pass while the view stays blank, because none of them cover the page's own handshake. Work from the server outwards and stop at the first thing missing:

- `initialize` answers with `resources` among its capabilities; `resources/list` returns the `ui://` uri; `tools/list` carries `_meta.ui.resourceUri`; `tools/call` carries `structuredContent`. A probe with `curl` settles all four.
- The host's log is the next witness. It records the client's own `capabilities.extensions["io.modelcontextprotocol/ui"]` declaration, and whether it asked for `resources/read` before the `tools/call` — a host only reads the resource when it intends to render it, so that pair in the log means everything above it worked and the remaining fault is inside the page.
- Then the three mandatory messages above. **A hand-written host simulator does not test them**: it answers whatever the page happens to send and ignores what the page fails to send, so it confirms the author's reading of the protocol rather than the protocol. Use a real host.

### A resource carries no data

**This is the rule, not a convention: a resource is a template, and the rows come from tools.**

`AbstractResource` is handed no `ManagedObjectContext`, which is what keeps this true rather than merely advised. Data reaches the UI through ordinary `tools/call` requests — through the host, with the connection's own credentials — so `AbstractTool::call()` applies RBAC, ownership and the row- and field-level rules exactly as it does for any other caller. Markup that embedded rows would answer them to anyone who can read the resource, with `FieldSecurityPolicy` never consulted.

The iframe's CSP is applied by the host from `_meta.ui.csp`; `SecurityHeadersTransformer` plays no part, because the markup travels inside the JSON-RPC body rather than as an HTTP response of its own.

### What this implementation does not do yet

The extension is opt-in: a host declares it in `initialize` under `capabilities.extensions["io.modelcontextprotocol/ui"]`, with the `mimeTypes` it renders. Two consequences, both deliberate omissions rather than oversights:

- **The server does not read that declaration.** It serves `resources/list`, `resources/read` and `_meta.ui.resourceUri` to every client, including one that renders no UI. Nothing breaks — a client that did not ask for the extension ignores the resources and the `_meta` — but the specification's advice to register UI-enabled tools only for a host that announced support is not followed, and a tool cannot ask whether it is answering a host that renders.
- **Tool `visibility` is not implemented.** The specification lets a tool be marked `["app"]` so only the UI may call it — paging, refreshing — and lets a host reject an app call to a tool whose visibility excludes it. Every tool here is visible to both the model and the UI.

Both wait for an App in production to justify them. See [SEP-1865](https://modelcontextprotocol.io/seps/1865-mcp-apps-interactive-user-interfaces-for-mcp) and the [ext-apps specification](https://github.com/modelcontextprotocol/ext-apps/blob/main/specification/2026-01-26/apps.mdx) for the full extension.
