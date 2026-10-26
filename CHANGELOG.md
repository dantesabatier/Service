# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project aims
to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html) from 1.0.0
onward.

## [Unreleased]

## [1.10.0] - 2026-10-26

### Added

- An agent run keeps the `structuredContent` a tool contributed. `LLMToolExecutionResult`, `LLMAgentToolResult`, the tool-result `LLMMessage` and `LLMToolCallResult` carry it as an optional `?array`, so `LLMRun::$toolCallResults` hands an application's chat the same data an MCP client receives — what it needs to render a tool's MCP App beside the answer. `InProcessLLMToolExecutor` takes it from the registry and `MCPToolExecutor` from the remote server, whose `MCPClient::callTool()` now reads the field and refuses one that is not an object. Providers never send it to the model, and a call answered from the run's cache carries none: it repeats no result. Every new parameter defaults to `null`, so existing callers are unchanged.

## [1.9.0] - 2026-10-26

### Changed

- **Breaking.** `AbstractTool::runJob()` is gone; `run_job` performs its own `JobOperation`, as every other tool now performs its own. With it the class holds nothing that reaches the store: what is left is being a tool — its name, title, description and schema, the MCP hints, the authorization a call is held to, and the three result builders.
- **Breaking.** `persistent_history` is two tools, `fetch_history` and `purge_history`, as HTTP has two strategies under one responder. The `operation` argument is gone with it: reading history and purging it are different things, and each tool now declares what it is — `fetch_history` is read-only, `purge_history` is destructive — where one tool answering to both had to advertise the weaker claim for every call. Their arguments are named after the Core Data factory methods they select, exactly as the HTTP endpoint names them: `afterDate`, `afterTransaction`, `afterToken` to read, `beforeDate`, `beforeTransaction`, `beforeToken` to purge, so the direction is in the name. `resultType` is the raw value of `PersistentHistoryResultType` rather than a case name. `ToolCallToPersistentHistoryChangeRequestAdapter` builds the change request, and the transaction filter is an ordinary fetch request built by the same adapter a query uses, as `PersistentHistoryResponseStrategy` builds it for HTTP. With them, `AbstractTool::executeHistory()`, `entityDescription()`, `buildPredicate()` and `resolveVariables()` are gone: nothing in the class reaches the store any more.
- **Breaking.** A write tool runs its own operation too, and the write tools are a hierarchy. `WriteTool` is the base `CreateTool`, `UpdateTool` and `DeleteTool` extend; it holds what a write needs — resolving the entity, `assertConcreteEntity()`, `normalizeRelationships()`, `shapeFromValues()` and `modify()`, for a tool whose change is a domain rule rather than a set of values — and each tool performs its own `Create`/`Update`/`DeletePersistentSpaceOperation`. `AbstractTool::create()`, `update()`, `delete()` and `modify()` are gone with them: the class is down to being a tool, not a data-access façade. Their `entity` argument is now `entityName`, as in the query tools.
- **Breaking.** A query tool runs its own operation. `AbstractTool::fetch()`, `count()` and `fetchRequest()` are gone and the managed object context is `protected`, so a tool builds the request and performs the operation itself — `new ReadPersistentSpaceOperation($this->context, $this->fieldSecurityPolicy, $request)->perform()` — exactly as `ReadPersistentSpaceResponseStrategy` does for HTTP. Executing data was never part of being a tool; it was a façade around a private context. The rules are unchanged: they live in the operation, which is what both sides run.
- **Breaking.** The query tools speak Core Data. `ToolCallToFetchRequestAdapter` translates a tool call into a fetch request the way `RequestToFetchRequestAdapter` translates an HTTP one, and the arguments are named after the properties they set: `entityName`, `predicate: {format, arguments}`, `serialization`, `sortDescriptors`, `fetchLimit`, `fetchOffset`, `fetchBatchSize`, `propertiesToFetch`, `propertiesToGroupBy`, `havingPredicate`, `includesSubentities`, `returnsDistinctResults`. `properties` and `relationships` are gone: they were a `serialization` written in two pieces and capped at one level, and `serialization` expresses any depth. `aggregate` and `group_by` take `propertiesToFetch` with Core Data expression descriptions instead of `{function, property, as}`, so any expression function is available — `median:`, `stddev:`, `mode:` — and a computed property can sit beside an ordinary attribute in the same projection, where the eight-function allow list permitted neither.
- **Breaking.** `count`, `aggregate` and `group_by` are `fetch`, and now extend it: a count is a fetch counted, an aggregate is a fetch projected through an expression, a grouping is that with group keys. They keep their own names, schemas, required arguments and hints — the name is what tells a model which case it is looking at — while one adapter builds every request. The tools no longer validate key paths or enum values: Core Data reports an unknown property when it generates the statement, and the rules the subject is held to are applied by the operation that runs the request.
- **Breaking.** An update is a modification, and is now one. `UpdatePersistentSpaceOperation` extends `ModifyPersistentSpaceOperation` and is nothing but a constructor: the change it names is applying the values the subject may write, where `Modify` receives whichever change it is given. Both declared the same authorization, read the same row and answered the same way, so there was one operation and two constructors.
- **Breaking.** The write side of the persistent space is one hierarchy too, and the sequence it runs is no longer optional. `WritePersistentSpaceOperation` holds it in a `final performCore()` — obtain the row, enforce the ownership and the resource rule on it, apply the change, save, read the stored row back — and `Create`, `Update`, `Delete` and `Modify` extend it, each declaring only which row it writes, which action that row is authorized for and what the change is. Before, the four repeated those steps by hand and nothing stopped a fifth from reordering or skipping one. `Create` populates the row as it obtains it, so the resource rule still sees the values being written, and it now runs the ownership check it previously skipped — inert unless a `create:own` or `any:own` scope is declared for the entity.
- **Breaking.** The read side of the persistent space is one hierarchy. `FetchPersistentSpaceOperation` is now the abstract base that holds the fetch request and folds the subject's read scope into it, and `ReadPersistentSpaceOperation` and `CountPersistentSpaceOperation` extend it, each overriding nothing but how that one request is consumed. The scope is applied in one place, so no subclass can read a row the subject may not see.
- **Breaking.** `AbstractTool::fetchObjects()` is gone, and with it the operation that returned unserialized objects. It was `fetch()` without the field filtering — a read with the `#[Readable]` enforcement left off, not an operation of its own. A tool computing over rows reads them through `fetch()` like every other, and enforces the columns it touches with `enforceFieldRead()` as before.

### Documentation

- MCP.md states what an App's markup must send to be rendered at all: `ui/initialize` with `appInfo`, `appCapabilities` and the extension's own `protocolVersion`, then `ui/notifications/initialized`, then `ui/notifications/size-changed` — an iframe cannot size itself, so a view that never reports its size is given no room whatever it draws. It also lists how to debug a view that stays blank while every server-side probe passes, and warns that a hand-written host simulator proves nothing: it answers what the page sends and ignores what the page omits.

## [1.8.3] - 2026-10-21

### Added

- MCP Apps. The server serves `resources/list` and `resources/read` and advertises the `resources` capability, so an application can publish a user interface a host renders beside the conversation. Drop a class extending `AbstractResource` in `src/MCPResources/` and it is discovered, exactly as a tool in `src/MCPTools/` is; `uiResourceUri` on a tool ties its answer to that interface, emitted as `_meta.ui.resourceUri`. A resource carries no data — it is handed no `ManagedObjectContext`, and the rows a UI shows arrive through ordinary tool calls where the caller's own rules apply. Two parts of the extension are not implemented yet: the client's `capabilities.extensions` declaration is not read, so resources are served to every client, and tool `visibility` (a tool only the UI may call) is absent.
- A tool's answer also travels as data. `tools/call` carries `structuredContent` beside `content`, so a client that renders a UI reads the rows themselves rather than parsing the text written for the model. Every tool answering through `jsonResult()` contributes it without changing a line, and `AbstractTool::structuredResult()` lets a tool whose text is prose contribute one too. The field is left out of a session that negotiated 2025-03-26, which does not define it.

### Fixed

- An unknown JSON-RPC method is answered with `Method not found` (-32601) rather than `Invalid params` (-32602). A client probing for a method the server does not implement was told its parameters were wrong, which says nothing about what it actually asked.

## [1.8.2] - 2026-10-21

### Fixed

- Tool use works with Gemini 3 through the OpenAI-compatible client. Gemini attaches a thought signature to each tool call under `extra_content` and rejects the next request without it; `LLMToolCall::$extraContent` now keeps that data and `StandardLLMClient` sends it back with the call.
- A model request waits as long as `LLMClient::$timeoutIntervalForRequest` says. The clients built their requests with `URLRequest`'s 60-second default, and the transport uses the lesser of the request's and the session's interval, so every model was cut off at 60 seconds whatever the client was configured to wait.
- A provider error wrapped in an array, as Gemini sends it, reports its message alone like the others instead of the raw JSON.

## [1.8.1] - 2026-10-20

### Fixed

- A run that stops on `providerFailure` or `toolProviderFailure` keeps the provider's own account of the failure in `LLMRun::$failureReason`. The runtime caught the exception and kept only the stop reason, so a rejected key, an unknown model and a rate limit all reached the caller as the same failure; a subagent's tool-provider failure carries its reason up to the root run.
- A provider failure whose body carries an error message reports that message alone, rather than the status followed by the raw JSON; a body without one is still appended whole.

## [1.8.0] - 2026-10-16

### Added

- `stream_has_started()` tells whether a streamed response already sent its headers, from what was actually sent.

### Fixed

- A stream that fails midway, by an exception or a fatal error, ends with its error response as the last line. The response used to end silently after the batches already sent, under a 200, so the client took a partial result as the whole one.

## [1.7.0] - 2026-10-15

### Added

- The `fetch` MCP tool takes a `batchSize` argument that reads the result from the store in batches. The result is returned whole and unchanged; each batch releases the objects already serialized, so a large result no longer holds its whole graph in memory.

## [1.6.8] - 2026-10-14

### Fixed

- A streamed response carries all of its headers. The stream emitter flushed while registering them, so only the first one was sent and a browser blocked the response for lacking CORS.
- A streamed response no longer declares `Transfer-Encoding: chunked`. The body is not framed in chunks; the web server applies the encoding to a body without a `Content-Length`.

## [1.6.7] - 2026-10-13

### Fixed

- A read with `fetchBatchSize` streams its batches as NDJSON. The response went through `JSONTransformer`, which encoded every batch at once, got an ETag computed over its whole body, and then failed to emit because `send()` handed the body to the stream emitter as content. Each batch is now encoded as it is emitted, so the memory of a large read no longer grows with its rows.

## [1.6.6] - 2026-10-08

## [1.6.5] - 2026-10-07

### Changed

- The MCP `describe_model` tool reports each relationship's `deleteRule` (`cascade`, `nullify`, `deny` or `noAction`), and the `delete` tool description explains how each rule affects the related rows, so an agent deletes only the parent of a cascading relationship instead of its children first.

## [1.6.4] - 2026-10-06

### Changed

- The MCP `update` tool description, `MCP.md`, `API.md` and `ARCHITECTURE.md` state that an update must carry at least one of the row's own attributes. Values with only relationships leave the row unmodified, so its `willSave()` does not run and the attributes it derives keep their previous values.

## [1.6.3] - 2026-10-03

### Fixed

- A `where` condition on `#[Readable]` that references a substitution variable (`$TODAY`, `$MONTH_START`, `$REMOTE_ADDRESS`, …) failed when narrowing a fetch: the read predicate reached SQL with the variable unbound, so it was emitted as a column name (`Unknown column '$REMOTE_ADDRESS' in 'WHERE'`). `FieldSecurityPolicy::resourceReadPredicate()` now binds the same variables `AccessConditionResolver::evaluate()` binds in memory.

## [1.6.2] - 2026-09-28

### Removed

- **Breaking:** `AbstractTool::execute()`, `authorize()`, `applySecurityScope()`, `ownershipPredicate()`, `enforceOwnership()`, `enforceResourceAccess()`, `applySecureUpdate()` and `applySecureRead()`. A tool implements `executeCore()`, now abstract, and reads and writes through `fetch()`, `count()`, `fetchObjects()`, `create()`, `update()`, `delete()` and `modify()`; `ToolRegistry` invokes it through `call()`.

### Changed

- **Breaking:** `AbstractTool::$context` is private. A tool that reached it reaches the data through the helpers above.

## [1.6.1] - 2026-09-28

### Fixed

- `AbstractTool::modify()` accepts a closure typed with the entity's own class, such as `fn(Project $project)`, without Psalm reporting an argument type coercion.

## [1.6.0] - 2026-09-28

### Added

- `AbstractTool::modify()` writes through a domain change instead of a set of values: the row is looked up within the read scope, ownership and the resource-level `#[Writable]` are enforced on it, the change runs, the context is saved, and the stored row is answered filtered to the fields the caller may read. The rules apply to the row named, not to the rows the change reaches through it.

## [1.5.0] - 2026-09-28

### Added

- `AbstractTool::executeHistory()`, `runJob()`, `tools()` and `entityDescription()`, so a tool can run a persistent history request or a job, read the tool catalogue, or look an entity up without reaching `$this->context`. `/history`, the CLI `JobRunner` and the MCP `persistent_history` and `run_job` tools run history requests and jobs through the same operations.

## [1.4.0] - 2026-09-28

### Added

- `AbstractTool::fetch()`, `count()` and `fetchObjects()` execute a tool's `FetchRequest` through the same read operation `PersistentSpace` uses over HTTP: the ownership scope and the resource-level `#[Readable]` are always folded in, and `fetch()` filters each object to the fields the caller may read. A tool that reads through them no longer calls `applySecurityScope()` or `applySecureRead()` itself.
- `AbstractTool::create()`, `update()` and `delete()` write through the same operations `PersistentSpace` runs over HTTP, so a tool that writes through them no longer calls `enforceOwnership()`, `enforceResourceAccess()`, `applySecureUpdate()` or `applySecureRead()` itself.
- `AbstractTool::call()` is the only way to invoke a tool: it authorizes the call and then runs `executeCore()`, the method a tool now overrides. `ToolRegistry` uses it, so no caller can run a tool without authorizing it first.

### Changed

- `phpcs.xml`, `phpstan.neon` and `.php-cs-fixer.dist.php` are gone. PHPUnit,
  Psalm and Rector are the tools this project uses and the three CI runs;
  the other configs were an invitation to findings no review would ask a
  contributor to act on.
- **Breaking:** a `PATCH` or `DELETE` through `PersistentSpace` looks the row up within the caller's read scope, as the MCP `update` and `delete` tools already did. A row the caller cannot read — narrowed out by an `own` read scope or by a resource-level `#[Readable]` — answers `404 Not Found` instead of being modified or deleted.
- The MCP `create` and `update` tools answer with the row fetched again after the save, like the HTTP response, so the answer carries what the store persisted. A `create` whose values name an existing `objectID` fails with a conflict, as a `POST` already did.

### Deprecated

- `AbstractTool::execute()`. Override `executeCore()` instead and invoke a tool with `call()`. A tool that still overrides `execute()` keeps working, and calling `execute()` on one that does not raises a deprecation notice and goes through `call()`.

## [1.3.4] - 2026-09-26

### Added

- The lightweight `describe_model` index marks abstract entities, so an LLM can see which entities cannot be instantiated without requesting their full schemas.

## [1.3.3] - 2026-09-26

### Added

- A `where` condition on `#[Readable]` or `#[Writable]` can reference `$REMOTE_ADDRESS`, the TCP peer address of the request (`REMOTE_ADDR`, never a forwarding header), for example `where: "\$REMOTE_ADDRESS == %@"`. Outside an HTTP request it is null.

## [1.3.2] - 2026-09-25

### Fixed

- Updating a user no longer invalidates every user's tokens. A `PATCH` whose body carries the user's roles rewrote each role's `name` with its own value, and any save touching a role or an authorization incremented every user's `refreshTokenVersion`. `AuthorizableTokenInvalidator` now compares the pending changes against the store and increments it only for the users whose permissions actually change: a user who gains or loses a role (from either side of the relationship), the members of a role whose name or authorizations change or that is inserted or deleted, and the members of the roles of an authorization whose name, type, scope or roles change or that is inserted or deleted.

## [1.3.1] - 2026-09-25

### Fixed

- Basic and Digest credentials that are not valid UTF-8 are rejected with `401 Unauthorized` rather than reaching the user lookup, where a Latin-1 username made the request fail with a `500`.

## [1.3.0] - 2026-09-24

### Added

- `AuthorizationService::authorizations()`, the authorizations an entity holds through its roles, read from the in-request cache, the persistent cache or the database in that order, and `authorizationScopes()`, the same authorizations as the `resource:action:scope` strings a JSON Web Token carries. `isAuthorized()` resolves through the first, so its behaviour is unchanged.

### Changed

- **Breaking:** a user authenticated with Basic or Digest carries authorization scopes, so an `own` scope restricts them as it restricts a JSON Web Token's bearer. Their scopes were empty, which left `own` unenforced: a user granted `Order:read:own` read every order and could modify other users'. `Authentication::$authorizationScopes` now reads them from `AuthorizationService::authorizationScopes()`, the cache `isAuthorized()` already fills, so no query is added; `BearerAuthentication` keeps the scopes its token carries. `AuthenticationContext` takes the `AuthorizationService` as an optional last argument, which `AuthenticationManager` passes; without it the scopes stay empty.

## [1.2.0] - 2026-09-24

### Added

- `AuthorizationType::forHTTPMethod()`, the action an HTTP method performs, shared by `AuthorizationEvaluator` and `PersistentSpace`.

### Changed

- **Breaking:** an `own` authorization scope restricts only the action it names. `FieldSecurityPolicy::hasOwnScopeFor()` and `enforceOwnership()` take the `AuthorizationType` being performed. Before, any `own` scope on an entity restricted every action on it, so `Order:read:own` also kept a user holding `Order:delete:all` from deleting another user's order. When the user's roles grant the same action on both `own` and `all` rows, `all` wins, as it already did for entity-level authorization. Neither `PersistentSpace` nor a tool passes the action: `PersistentSpace` takes it from the request method, and `AbstractTool::enforceOwnership()` from the action `authorize()` checked, so ownership is judged against the same action the call was authorized for. A tool that calls `enforceOwnership()` outside a call authorized through `ToolRegistry` now fails with an `InternalInconsistencyException` while security is enabled.

## [1.1.1] - 2026-09-24

### Fixed

- The MCP server reports the release version during initialization, and `MCPClient` reports the same version by default. 1.1.0 was published still reporting 1.0.0, because `MCPServerVersionDefault` and the client's default are not derived from `Info.plist`.

## [1.1.0] - 2026-09-24

### Added

- `AccessPolicy::allowsAccess()`, the decision whether a user may perform an action on a resource. `AuthorizationEvaluator` asks it rather than `AuthorizationService` directly, and `PublicAccessPolicy` answers yes.
- `AbstractTool::authorizationResource()` and `authorizationAction()`, the resource and action a tool call is authorized against, and `authorize()`, which asks `allowsAccess()` for them. They default to the `entity` argument and to `read`, or `update` when the call is not read-only.

### Changed

- **Breaking:** `ToolRegistry` authorizes every tool call before running it, so entity-level RBAC no longer depends on each tool checking it inside `execute()`. A custom tool that takes an `entity` argument is now authorized against it without writing anything: `read` when the call is read-only, `update` otherwise. Override `authorizationResource()` or `authorizationAction()` when that default does not describe the call, and return a null resource when it needs no authorization. The built-in tools authorize exactly what they did before.
- Upload failures are reported in the active language. The messages `UploadsEnumerator` produces bypassed `localized_string`, so they stayed in English under every locale; the English and Spanish catalogs carry them now. `JobTool`'s strings are excluded from extraction, so they no longer reach the application catalog.

## [1.0.0] - 2026-09-18

The initial public release. The framework had been in use in private projects
before it; the entries below record what it does and the work that made it
publishable.

### Added

- The Service application framework, providing the HTTP and application layer
  of the Sabatier SDK alongside Foundation and CoreData.
- A responder chain with attribute-based endpoints, actions, response
  transformers, built-in authentication routes and automatic responder
  discovery.
- `PersistentSpace`, exposing model entities as REST resources with automatic
  fetch, count, create, update and delete operations.
- Integrated authentication and authorization with Basic, Bearer/JWT and Digest
  strategies, session fallback, role and scope evaluation, ownership rules, and
  field- and resource-level access controls.
- Infrastructure response processing for cache policy, conditional requests,
  rate-limit headers, security headers, CORS and response-header sanitization.
- Configurable rate limiting and idempotency with APCu, Redis, Memcached and
  process-local store implementations.
- Static-resource, upload and download handling governed by explicit resource
  and file-transfer policies.
- Server-side rendering through `ViewController`, `View`, `Renderer` and
  `#[Outlet]`, plus Server-Sent Events through `EventStreamResponse`.
- A CLI job runtime with automatic discovery from `src/Jobs/`, shared Core Data
  context configuration and consistent transaction authorship.
- A stateful MCP server at `/mcp` supporting protocol revisions `2025-03-26`,
  `2025-06-18` and `2025-11-25`.
- Twelve built-in MCP tools: `describe_model`, `fetch`, `count`, `aggregate`,
  `group_by`, `create`, `update`, `delete`, `persistent_history`, `run_job`,
  `get_server_time` and `web_search`.
- Automatic MCP tool discovery from `src/MCPTools/`, model-derived JSON Schema,
  localized vocabulary and predicate guidance.
- `MCPClient`, `StreamableHTTPMCPTransport` and `MCPToolExecutor` for using a
  remote MCP catalogue through the same agent tool boundary as in-process tools.
- Provider-neutral LLM clients for OpenAI-compatible Chat Completions,
  Anthropic Messages and native Ollama chat APIs.
- Opt-in Anthropic prompt-prefix caching, preserving explicit request-body
  overrides and including cached input in agent token budgets and trace totals.
- `LLMAgent` with a replaceable orchestration loop, in-process or remote tool
  execution, shared deadlines, bounded subagents and normalized run outcomes.
- `PlanExecuteLLMAgentLoop` for tasks that must submit an ordered plan before
  real tools execute, plus a reusable runtime-backed loop conformance suite.
- `LLMExecutionPolicy` for tool, subagent and token budgets and per-call approval
  of state-changing tools.
- Replaceable context assembly through `LLMContextAssembler` and
  `WindowedLLMContextAssembler`, including complete-turn truncation and optional
  summaries.
- Backend-neutral retrieval through `LLMRetriever` and
  `RetrievalAugmentedLLMContextAssembler`, with bounded documents and preserved
  provenance.
- Structured agent observability through `LLMRunObserver`, immutable run events,
  hierarchical run contexts and injectable clocks.
- Advisory MCP tool annotations on `tools/list`, built by `ToolRegistry` from
  four `AbstractTool` property hooks: `isReadOnly` publishes `readOnlyHint`,
  `isDestructive` publishes `destructiveHint`, `isIdempotent` publishes
  `idempotentHint` and `isOpenWorld` publishes `openWorldHint`. The defaults are
  the conservative end of each — a tool says nothing about its effects unless it
  overrides one — so an existing custom tool keeps its meaning. `isReadOnly`
  describes every operation a tool exposes rather than one call, which is why
  `persistent_history` advertises `false` while `isReadOnlyCall()` still admits
  its fetches as reads. `ToolDescriptor::$annotations` is optional for a
  hand-supplied catalogue, an empty object survives serialization as one, and
  `MCPClient` keeps a remote server's hints while rejecting a malformed known
  field instead of coercing it to a boolean. These are hints, not authorization:
  `MCPToolExecutor` still requires application-supplied trusted classifiers, and
  neither the annotations nor a remote hint enable retries or result caching.
- Public documentation for architecture, HTTP clients, MCP tools, LLM agents,
  security reporting, contribution and release preparation, plus a complete
  `.env.example` configuration reference.
- `CONTRIBUTING.md` now points at
  [Foundation's `CONVENTIONS.md`](https://github.com/dantesabatier/Foundation/blob/master/CONVENTIONS.md)
  for the mechanical conventions shared across the stack, by absolute URL rather
  than as a vendored copy — four copies of one checklist drift, and then nobody
  knows which is authoritative. The section also records the two places a reader
  running its searches gets a hit that is not a defect: the MCP tools keep native
  arrays for `$inputSchema` and `$outputFormat`, which are JSON Schema literals
  serialized straight to the wire and read only by their known keys, while their
  `execute()` surface does take a `Dictionary` and answer an `ArrayClass`; and
  the classes that are not `final` — `Application`, `Emitter`, `Renderer`,
  `Response`, `View`, `CacheHeaderTransformer`, `UnauthorizedException` — are
  extension points for the applications built on the framework, so the "final
  unless something extends it" search reports them whether or not anything in
  this tree does. Two of the document's rules were not being held and are fixed
  with it: `Endpoint.php` and the two MCP schema builders documented themselves
  in Spanish, and six files carried comment prose hard-wrapped at a column the
  editor then soft-wraps again.
- `EventStreamResponderTest` asserts what it was written to assert. All eight of
  its tests errored before reaching an assertion: the fixture reaches the
  framework through `new Request()`, whose constructor calls `request_url()`,
  which answers an empty string with no `$_SERVER` populated, and the `URL`
  constructor rejects that. The helper does assign a real URL on the line after
  the fixture is built, which is why the omission read as deliberate rather than
  as a gap — but a constructor that has already failed is never reached. `setUp()`
  supplies `HTTP_HOST` and `REQUEST_URI` the way the transport-guard, CORS and
  conditional-GET suites already do, and `tearDown()` restores `$_SERVER` so the
  suite stays independent of the ones around it. The thirty assertions that
  appear with it are the ones these tests always meant to make.

### Changed

- **Breaking.** `EventStreamResponder` is now a public abstract base. Concrete
  subclasses declare their own `#[Endpoint]` and provide a protected `$events`
  hook returning a `Closure(): Generator<int, ServerSentEvent>`. The base keeps
  GET handling, session management and response transformers, without depending
  on Core Data polling or a fixed `/Events` route. Code directly instantiating
  the former internal responder must provide a concrete subclass instead.
- **Breaking.** `UploadsEnumerator::__construct()` takes the subdirectory as a
  `string` where it took a resolved `URL $directoryURL`. The enumerator no
  longer receives a location: it is handed the component the request named and
  asks `FileTransferPolicy` where that resolves, which is what keeps the
  destination out of reach of anything the client sends. `$directoryURL`
  survives as a read-only property, so code reading it is unaffected; code
  constructing the enumerator is not. The class is `@internal` and `Uploader` is
  its only caller inside the framework.
- `PUT` is dispatched as a mutation alongside `POST`, `PATCH` and `DELETE`, so a
  responder declaring a `#[Action(method: HTTPRequestMethod::put)]` is now
  routed to it. No built-in responder handles `PUT`; the framework opens the
  path and leaves the verb to the application.
- `LLMAgent` now denies state-changing tool calls by default. Applications must
  provide an `LLMExecutionPolicy` whose approval closure accepts the concrete
  calls authorized by the user. A denied call stops with
  `LLMRunStopReason::writeApprovalRequired`.
- Agent limits now apply across the complete run tree. Provider turns, tools,
  retries and subagents share one monotonic deadline and cumulative tool,
  subagent and token budgets.
- Remote MCP tools are considered state-changing and non-cacheable unless the
  application supplies trusted classifiers for the concrete call.
- `/mcp` now requires the stateful initialization lifecycle. Calls after
  `initialize` carry the issued `Mcp-Session-Id`; `DELETE /mcp` ends the
  session, and active sessions refresh their configured idle lifetime.
- `POST /download` requires its `url` to resolve to exactly one allowed
  directory and one filename. Deeper, malformed or disallowed paths return
  `400 Bad Request`.
- Store implementations are explicit application choices. The default
  rate-limit, idempotency and MCP session stores use APCu; Redis, Memcached and
  process-local alternatives are selected in application configuration.
- Tool result caching now depends on `AbstractTool::$isCacheable`, independently
  of whether a tool is read-only. Mixed tools can classify each invocation with
  `isReadOnlyCall()`.
- `ExtractLocalizables.php` extracts the localizations the bundle declares
  rather than a hard-coded English and Spanish pair, so adding a locale to
  `Info.plist` is enough for the extractor to pick it up.

### Removed

- Unused chat and widget instruction environment-key constants that were never
  read by a framework surface.

### Fixed

- Provider responses with malformed or missing messages can no longer be
  mistaken for successful, empty assistant turns. Output limits and refusals
  remain distinct incomplete outcomes.
- OpenAI-compatible token usage is read from Chat Completions and Responses API
  field names; Anthropic preserves every text block and accepts `extraBody`;
  failed OpenAI- and Ollama-shaped tool results are marked clearly for the
  model.
- Tool calls from every provider now require a usable identifier, name and
  argument object. Malformed JSON and list-shaped arguments fail instead of
  becoming an empty dictionary.
- Tool arguments stay JSON objects on the wire, at every depth. A `Dictionary`
  represents an object whether or not it holds anything, but its backing array
  does not: an empty one encodes as `[]`, and so did an empty nested object
  inside a populated call. Every provider requires an object there, so a tool
  invoked with no arguments — or with an argument whose own object was empty —
  sent a shape the provider rejects or misreads. `LLMClient::toolArguments()`
  now converts a `Dictionary` to a `stdClass` recursively, keeping lists as
  lists, and Anthropic, the OpenAI-compatible client and Ollama all render
  through it.
- Tool schemas now reject unknown and missing required arguments before
  execution, preventing misspelled filters from silently producing unfiltered
  reads.
- Tool restrictions narrow the registry itself, so restricted tools are absent
  from raw calls, model catalogues and agent execution consistently.
- Repeated state-changing calls are no longer served from the agent's run-local
  cache. Time-sensitive tools such as `get_server_time` can remain read-only
  without becoming cacheable.
- `count` and `aggregate` resolve temporal predicate variables consistently with
  the other predicate tools, and result summaries follow rather than interrupt
  returned rows.
- Agent run results now distinguish provider failure, tool-provider failure,
  deadline, iteration cap, refusal, output limit, context limit, approval and
  every execution-budget boundary. `LLMRun::$isRetryable` reports whether a
  later attempt is meaningful.
- Subagents inherit the remaining root deadline and the exact parent system
  prompt; they cannot replace policy or reset resource limits.
- Session and JWT logout both return `204 No Content`.
- Upload transport errors such as exceeding `upload_max_filesize` return a
  client error with the actual reason instead of failing later as a server
  error.
- A transfer component is judged by what it could reach, not by how it reads.
  `FileTransferComponent` required `/^[A-Za-z0-9_-]+$/` of each piece, which
  refused names that cannot name any other location: anything carrying a space,
  an accent or an eñe — in a framework whose data is in Spanish — and
  `respaldo.tar.gz` with them. The containment the transfer surface rests on is
  that `documentRoot/{directory}/{filename}` is inside the document root by
  construction, and that needs only that neither name can name a location: no
  separators, no dot components, no NUL, not empty, and within the length of one
  path component. That is the rule now. A leading dot stays refused, which
  settles `.` and `..` along the way; for the remaining dotfiles the reason is
  not traversal but that an upload has nothing behind it to mediate, and writing
  `.htaccess` into a served directory has consequences. A valid name is stored
  exactly as it arrived, with no rewriting.
- HTTP persistent-history reads and purges now reject absent or empty scope
  values instead of constructing an unbounded request; transaction `0` remains
  an explicit boundary rather than being mistaken for no value.
- HTTP history documentation, aggregate expression types, error envelopes,
  lifecycle timing, store defaults, MCP defaults and tool names now match the
  implemented runtime.

### Security

- State-changing agent tools require explicit approval, and coercive budgets
  bound parent and subagent activity independently of model instructions.
- Subagents inherit the parent's system prompt exactly, closing a path where a
  delegated task could replace policy while retaining real tools.
- Retrieved documents are framed as untrusted evidence with per-assembly
  unguessable boundaries and single-line provenance, preventing document text
  from escaping its declared data boundary.
- Upload directory and filename components are validated before filesystem use;
  traversal components are rejected, temporary files must be genuine HTTP
  uploads, and transport-level upload failures are enforced.
- Download paths are resolved before authorization and pass through the same
  public/protected and dotfile policy used by static resources.
- Rate-limit keys always include the remote address, preventing an
  unauthenticated caller from exhausting another subject's quota or evading the
  anonymous limit by rotating claimed usernames.
- `MCPTransportGuard` enforces allowed browser origins, supported protocol
  versions and the session established by initialization before dispatch.
- MCP sessions are scoped to the authenticated subject, so an identifier issued
  to one identity cannot be used by another.
- `JSONWebTokenAudienceEvaluator` enforces `MCP_TOKEN_AUDIENCE` when configured,
  allowing deployments to issue credentials specifically for the MCP endpoint.
