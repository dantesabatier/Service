# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project aims
to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html) from 1.0.0
onward.

## [Unreleased]

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
