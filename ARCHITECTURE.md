# Service Framework — Architecture Guide

This document describes the internal architecture of the Service framework: how requests are received, routed, secured, and answered; how persistence integrates into that pipeline; and how the built-in subsystems — REST automation, MCP, event streaming, and server-side rendering — fit together.

---

## Table of Contents

1. [Design Philosophy](#1-design-philosophy)
2. [Application Lifecycle](#2-application-lifecycle)
3. [Request Pipeline](#3-request-pipeline)
4. [The Responder Chain](#4-the-responder-chain)
5. [Routing: Endpoints and Actions](#5-routing-endpoints-and-actions)
6. [Built-in Responders](#6-built-in-responders)
7. [PersistentSpace — Automatic REST](#7-persistentspace--automatic-rest)
8. [The Response Pipeline](#8-the-response-pipeline)
9. [Security Architecture](#9-security-architecture)
10. [Rate Limiting and Idempotency](#10-rate-limiting-and-idempotency)
11. [CORS and Security Headers](#11-cors-and-security-headers)
12. [MCP Server](#12-mcp-server)
13. [Event Streaming](#13-event-streaming)
14. [Server-Side Rendering](#14-server-side-rendering)
15. [Scheduled Jobs](#15-scheduled-jobs)
16. [Application Delegate](#16-application-delegate)
17. [Environment Configuration Reference](#17-environment-configuration-reference)

---

## 1. Design Philosophy

Service is built on three convictions that shape every design decision.

**Convention over configuration, but configuration over magic.** The framework makes strong choices — request routing without tables, REST APIs without controllers, security without decorators — but every default is an explicit, named class that you can replace. There is no runtime reflection that hides what is happening.

**The model is the source of truth.** The Core Data schema drives everything: it generates the REST surface, the authorization query, the MCP tool catalogue, and the field-level access rules. You define the domain once; the framework derives the infrastructure from it.

**Security is structural.** Authentication, authorization, field-level access control, rate limiting, and idempotency are not middleware stacks you assemble. They live in the pipeline, run unconditionally on every request, and require an explicit decision to relax — not to enable.

---

## 2. Application Lifecycle

`Application` is a singleton (`Application::shared()`) that serves as both the root of the responder chain and the container for all shared services: the Core Data stack, authentication, authorization, policies, and session.

When `run()` is called, the application executes a fixed sequence of steps before any user code runs:

1. **Bootstrap** — discovers and initializes the application delegate; registers authentication strategies and JWT signing algorithms.
2. **Preflight** — if the request is a CORS preflight (`OPTIONS` with an `Origin` header), it is answered immediately and the pipeline exits.
3. **Application initialization** — notifies the delegate (`applicationWillFinishLaunching`), loads the Core Data persistent store, and registers a shutdown handler that catches fatal PHP errors and converts them to structured error responses.
4. **Rate limiting** — increments the counters for the current client and rejects the request with `429 Too Many Requests` once either is spent. Every counter is keyed on the remote address: this step runs before any credential is verified, so a claimed username is not something the key may rest on. See §10.
5. **Access enforcement** — resolves the first responder for the request and evaluates the full access evaluator chain. If the chain rejects the request, a `401 Unauthorized` or `403 Forbidden` response is emitted immediately.
6. **Transaction author** — wires the authenticated user's identity into the Core Data context so that persistent history records who performed every write.
7. **Response** — asks the first responder to produce its response and sends it.

Any uncaught `Throwable` at any step is caught and forwarded to `ErrorResponder`, which produces a structured JSON error response with the appropriate status code and, in development mode, the exception message.

---

## 3. Request Pipeline

```
Incoming HTTP request
        │
        ▼
   Preflight?  ──yes──▶  CORS response (exit)
        │ no
        ▼
   initializeApplication()
        │
        ▼
   enforceRateLimitIfNeeded()
        │
        ▼
   checkAccessPermissions()  ◀── resolves firstResponder (lazy)
        │
        ▼
   setTransactionAuthor()
        │
        ▼
   firstResponder→response
        │
        ├── user pipeline (transformers from #[Endpoint]/#[Action])
        └── infrastructure pipeline (cache, security headers, CORS, rate-limit headers)
        │
        ▼
   response→send()
```

The distinction between the user pipeline and the infrastructure pipeline is important: user transformers (JSON serialization, HTML rendering, download formatting) are declared per-endpoint and can vary; infrastructure transformers (`SecurityHeadersTransformer`, `CORSResponseTransformer`, `RateLimitHeaderTransformer`, `CacheHeaderTransformer`, `ConditionalGetTransformer`) run on every response regardless of the endpoint and cannot be removed.

---

## 4. The Responder Chain

The responder chain is an ordered linked list of `Responder` objects. The framework builds it once per request by concatenating the custom responders discovered from the application's source tree with a fixed sequence of built-in responders. The chain is headed by `AuthenticationManager`, which `Application` hands to `FirstResponderResolver` as the default responder, so the login, logout and refresh routes are matched before anything else claims the request:

```
[custom responders from src/Responders/ and src/ViewControllers/]
    → AuthenticationManager
    → PersistentSpace
    → PersistentHistoryResponder
    → ResourceManager
    → Preferences
    → Uploader
    → Downloader
    → MCPResponder
    → HomeController
```

The framework walks this chain and calls `isFirstResponder` on each node. The first node that returns `true` for the current request URL becomes the active responder. If no node matches, a `NotFoundException` is thrown.

Each responder holds a reference to the next one via `$nextResponder`. This structure mirrors AppKit's responder chain: a responder that does not handle a request can forward it down the chain, and the chain itself is a first-class object that can be inspected.

`Responder` is the base class. It exposes:
- The current `Request` and `Session` as shared, lazily-initialised properties.
- The `ManagedObjectContext` (Core Data's view context) for data access.
- All active policies (`corsPolicy`, `cachePolicy`, `rateLimitPolicy`, etc.) inherited from `Application` via property hooks.
- The `$response` property, which executes the full response lifecycle: method validation, session management, idempotency, action dispatch, and pipeline execution.

---

## 5. Routing: Endpoints and Actions

Routing is entirely attribute-driven. There are no routing tables, route registrars, or configuration files.

**`#[Endpoint(path, transformers)]`** marks a class as a GET handler. The `path` is matched against the incoming URL. If omitted, the class name is used as the path. `transformers` is the ordered list of response transformer classes applied to every response from this endpoint.

**`#[Action(method, path, transformers)]`** marks a method as handling a mutating request (POST, PATCH, DELETE, PUT). The `path` defaults to `/{methodName}`. When a mutating request arrives, the framework calls the matching action method, which sets `$this->data` and `$this->statusCode` as side effects, then passes the response through the action's own transformer chain.

`ResponderResolution` handles the matching: it reads the `#[Endpoint]` and `#[Action]` attributes on the class at construction time, compares the URL path against the configured routes, and returns whether the responder claims the request and, if so, which action method to call.

The effect is that routing is co-located with the handler code. Every `Responder` subclass is self-describing: its route, the HTTP methods it accepts, and the transformers it applies are all visible in the class declaration.

---

## 6. Built-in Responders

These responders are always present in the chain, in this order of priority after custom responders:

**`PersistentHistoryResponder`** exposes the Core Data persistent history log at `/history`. It accepts `GET` (fetch transactions) and `DELETE` (purge transactions). Both verbs accept an optional scoping parameter: fetch uses `afterDate`, `afterTransaction`, or `afterToken`; delete uses `beforeDate`, `beforeTransaction`, or `beforeToken`. Omitting the parameter targets the full history. `GET` returns a JSON payload; `DELETE` returns `204 No Content`. Tokens are passed as base64-encoded JSON. This responder requires persistent history tracking to be enabled via `PersistentHistoryTrackingKey` in `UserDefaults`; `Application` sets that option automatically when configured. It overrides `$response` directly because `GET` and `DELETE` produce fundamentally different response shapes (body vs. bodyless) that cannot be unified through the `$data` hook.

**`ResourceManager`** serves static files. It delegates the decision to `StaticResourcePolicy`, which determines whether the URL maps to a physical file in a public directory and how it should be cached. The `StaticResourceDisposition` it returns carries a `maxAge` and an `immutable` flag, which `ResourceManager` emits through `StaticCacheHeaderTransformer` in the user pipeline — independently of the application's `HTTPCachePolicy` (which targets dynamic API responses). Public bundle resources (`Resources`, `vendor`, `node_modules`, …) get `public, max-age=N, immutable` with `N` defaulting to one year and overridable via `STATIC_RESOURCE_MAX_AGE`; optional browser files like `favicon.ico` and `robots.txt` get a shorter `public, max-age`; everything else gets `no-cache`. Extra public directories — typically a bundler's output directory such as Vite's `Build` or `dist` — are declared through the `STATIC_PUBLIC_DIRECTORIES` environment variable (comma-separated, relative to the bundle root); their fingerprinted contents are then served with the same long-lived `immutable` policy. Because the user pipeline writes `Cache-Control` first, the downstream `CacheHeaderTransformer` leaves it untouched. Only `GET` and `HEAD` are accepted.

**`Preferences`** exposes the application's `UserDefaults` store at `/Preferences`. `GET` returns the full key-value dictionary as JSON; `PATCH` merges the request body into the store through its `synchronize` action. This is always no-cache.

**`Uploader`** receives `multipart/form-data` uploads at `/upload`. The request names a subdirectory, never a path; `FileTransferPolicy` resolves where each file lands and under what name, creates the directory if absent, moves each uploaded file from the PHP temporary location into place, and returns an array of the saved filenames. Both the subdirectory and each filename must be a single alphanumeric slug (`[A-Za-z0-9_-]`), stem and extension alike — a name that could describe another location is refused rather than adjusted, since storing a file under a name nobody asked for is harder to explain than a `400`. Existing files at the target path are silently replaced.

Two checks precede all of that. A file the transport itself rejected — over `upload_max_filesize`, arriving incomplete — is answered with `400` and the reason, rather than passing a size check with a size of zero and failing to move; and `is_uploaded_file()` confirms the temporary path was produced by this request's upload, so a `tmp_name` that names some other file on the server moves nothing.

Permissions default to `0777`, deliberately: a deployment whose writing and reading processes are different users cannot read back a stricter file, and what the endpoint serves is mediated by the responder chain rather than by the filesystem. Tighten it with `FILE_TRANSFER_FILE_PERMISSIONS` where the deployment allows. What may be uploaded is a question of authorization, not of file type — the endpoint sits behind the same RBAC every other responder does, and a role entitled to upload is trusted with what it uploads. `FILE_TRANSFER_ALLOWED_EXTENSIONS` is there for an application that wants to narrow that.

**`Downloader`** responds to `POST /download` with a file attachment. The request body contains the URL of the file to serve; its path is split into a directory and a filename, `FileTransferPolicy` resolves those two names against the document root, and the file is read, its MIME type and character encoding detected, and delivered with a `Content-Disposition: attachment` header. The POST method is used deliberately — the URL of the file to download is a parameter, not a path segment, which avoids exposing arbitrary file paths in GET URLs.

Only names travel to the policy, never a path, so nothing the request sends can describe a location outside the directory the policy chose — there is no path left to canonicalize or prefix-check. The location must be exactly one directory deep, matching the single level `Uploader` writes to. The default policy then asks `StaticResourcePolicy` about the resolved URL, so a download answers to the same rules a static request does rather than deciding on its own.

**`MCPResponder`** exposes the full Core Data model as an MCP tool server at `/mcp`. See [Section 12](#12-mcp-server).

**`HomeController`** is the chain's final fallback: it answers `GET /` with an HTML page rendered from a template, populated with the application name, version, and copyright from the main bundle's `Info.plist`.

---

## 7. PersistentSpace — Automatic REST

`PersistentSpace` is the framework's most powerful built-in responder. It activates whenever the last path component of the URL matches the name of a Core Data entity in the managed object model. No registration, no controller, no serializer — the entity's name in the schema is its URL.

For a model containing `User`, `Post`, and `Comment` entities, the framework automatically exposes:

```
GET    /User
POST   /User
PATCH  /User
DELETE /User
GET    /Post
...
```

Every endpoint is immediately functional, secured, and consistent.

### Read (GET)

Queries are expressed as URL parameters. There are two modes:

- **Simple filters**: each query parameter becomes a `field = value` predicate. Multiple parameters are AND-combined. Example: `GET /Post?status=published&authorID=42`.
- **Full fetch request**: a single `fetchRequest` parameter containing a base64-encoded JSON object that encodes the complete `FetchRequest` — including predicates, sort descriptors, pagination, result type, and serialization shape. This is the mode used by rich clients and by the MCP tools.

When a user's authorization scope is `own`, the framework automatically appends an ownership predicate to the query, so users only see records they own — at the database level, not by post-filtering in PHP.

When `fetchBatchSize` is set on the fetch request, the response is streamed: records are serialised and flushed incrementally rather than accumulated in memory, which is important for large result sets.

### Mutations (POST, PATCH, DELETE)

- **POST** inserts a new object. If the body includes an `objectID`, a uniqueness check runs first; a conflict throws `409`. The framework calls `applySecureUpdate` on the new object before saving, which hashes passwords for `Authorizable` entities and strips fields the user is not allowed to write.
- **PATCH** fetches the object by `objectID`, enforces ownership if applicable, applies the secure write filter, and saves only if the context has actual changes (avoiding unnecessary writes).
- **DELETE** fetches by `objectID`, enforces ownership, deletes, and returns `204 No Content`.

Every mutating operation re-fetches the object after saving and applies `applySecureRead` before returning it, so the response always reflects the committed state with fields filtered for the current user.

### Field-Level Security

Field-level security is declared on the managed object class with two PHP attributes:

- **`#[Readable(by: ['admin'], scope: AuthorizationScope::all)]`** — controls which roles can see a field in API responses.
- **`#[Writable(by: ['admin', 'owner'], scope: AuthorizationScope::own)]`** — controls which roles can write a field.

`FieldSecurityFilter` reads these attributes by reflection (cached statically per class after the first access) and removes restricted fields from the payload. If a field's scope is `own`, the user must also be the record's owner to access it.

### Ownership

A single `#[Owner]` attribute on a managed object property designates it as the owner field. `OwnerResolver` discovers it by reflection (also cached), and `OwnershipService` checks equality between the current user and the field value. Ownership enforcement is applied on both reads (predicate injection) and writes (`enforceOwnership` throws `403` if the check fails).

---

## 8. The Response Pipeline

Every response passes through two sequential transformer pipelines managed by `ResponsePipeline`.

**User pipeline** — transformers declared in `#[Endpoint]` and `#[Action]` attributes. These shape the content of the response: `JSONTransformer` serialises the body to JSON and sets `Content-Type: application/json`; `HTMLTransformer` renders the body as an HTML string; `DownloadResponseTransformer` sets attachment headers; `NoCacheHeaderTransformer` disables client caching for volatile responses.

**Infrastructure pipeline** — five transformers that run unconditionally after the user pipeline on every response:

| Transformer                  | Responsibility                                                                     |
|------------------------------|------------------------------------------------------------------------------------|
| `CacheHeaderTransformer`     | Writes `ETag` and `Cache-Control` headers from `HTTPCachePolicy`                   |
| `ConditionalGetTransformer`  | Evaluates `If-None-Match` / `If-Modified-Since` and returns `304` when appropriate |
| `RateLimitHeaderTransformer` | Appends `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`          |
| `SecurityHeadersTransformer` | Writes all security-related headers (CSP, HSTS, X-Frame-Options, etc.)             |
| `CORSResponseTransformer`    | Writes `Access-Control-*` headers if the request origin is permitted               |

`ResponseTransformerContext` is the shared object that carries the current request, all active policies, and the rate-limit state into every transformer. Transformers receive it at construction and read what they need from it.

`ErrorResponder` uses its own fixed pipeline — `JSONTransformer → ResponseHeaderSanitizerTransformer → RateLimitHeaderTransformer → SecurityHeadersTransformer → CORSResponseTransformer` — to ensure that error responses are always well-formed JSON with the same security headers as successful ones.

---

## 9. Security Architecture

Security is composed of several independent layers that each enforce a distinct concern. They are not middleware stacks — they are integrated into the framework's pipeline and run automatically.

### 9.1 Authentication

The framework detects the authentication scheme from the `Authorization` header and delegates to the matching strategy:

- **Basic** — decodes the `base64(username:password)` credential and verifies the password with `password_verify()` against the stored hash.
- **Bearer (JWT)** — decodes the JWT using the configured signing key, extracts the `sub` claim as the username, and trusts the token's validity (signature, expiry, and version are checked separately in the evaluator chain).
- **Digest** — implements RFC 7616 hash-response verification. Supports SHA-256, SHA-512-256, and MD5 algorithms.

All three strategies share a base class, `Authentication`, that lazily resolves the authenticated user entity from the database via `AuthenticationService`. The service scans the managed object model on first use to locate the entity class that implements the `Authorizable` interface, then fetches it by username with the minimum set of attributes needed for authentication.

Custom authentication strategies can be registered with `AuthenticationResolver::registerClass()`.

### 9.2 JWT

The JWT subsystem is a full implementation of the standard: header, payload, and signature are each typed objects. The signing algorithm is selected at runtime from the `JWTSignatureAlgorithmKey` environment variable (default: HS256).

A token issued at login contains:

- Standard claims: `iss` (host), `sub` (username), `exp`, `nbf`, `iat`, `jti` (random 16-byte ID)
- **`enb`** — the user's `isEnabled` flag, embedded in the token so the evaluator chain can check it without a database round-trip
- **`ver`** — the user's `refreshTokenVersion` counter, used to invalidate all outstanding tokens when the user logs out or changes credentials
- **`scp`** — technical scopes: `access` and/or `refresh`, which govern which endpoints the token can be used with
- **`authz`** — authorization scopes, strings of the form `resource:action:scope` (e.g., `posts:read:all`, `comments:create:own`), pre-computed at login from the user's roles so that most authorization decisions can be made without a database query

JWT coder strategies follow the same plugin pattern as authentication strategies: `JSONWebTokenCoderStrategyFactory` maintains separate registries for encoders and decoders, and the correct strategy is selected by matching the algorithm.

### 9.3 Access Evaluator Chain

`AuthenticationManager::$isProtectedContentAvailable` evaluates a chain of `AccessEvaluator` implementations in AND-short-circuit order. The chain differs depending on the endpoint:

For normal requests, the chain is:

1. **`SessionAuthenticationEvaluator`** — if JWT is not configured, confirms the PHP session is active and marked as authenticated.
2. **`AuthenticationEvaluator`** — verifies the credential is valid (password match or valid JWT structure).
3. **`JSONWebTokenScopeEvaluator(access)`** — verifies the token carries the `access` technical scope.
4. **`JSONWebTokenAccessTimeEvaluator`** — verifies `nbf ≤ now ≤ exp`.
5. **`JSONWebTokenEnabledEvaluator`** — verifies the `enb` claim is `true`.
6. **`JSONWebTokenVersionEvaluator`** — verifies the token's `ver` matches the user's current `refreshTokenVersion`. If the user has logged out or the token has been invalidated, this check fails.
7. **`JSONWebTokenAudienceEvaluator`** — verifies a bearer token presented to `/mcp` carries the audience `MCP_TOKEN_AUDIENCE` names. Inert on every other path, and inert everywhere while that variable is unset.
8. **`AuthorizationEvaluator`** — verifies the user has the required permission for the requested resource and HTTP method.

For refresh requests, the chain is shorter: `AuthenticationEvaluator` → `JSONWebTokenScopeEvaluator(refresh)` → `JSONWebTokenRefreshTimeEvaluator` → `JSONWebTokenEnabledEvaluator` → `JSONWebTokenVersionEvaluator`. It accepts only a refresh-scoped token, and drops the session, audience and authorization checks — a token refresh is not a resource access and does not require a resource permission.

`DefaultAccessPolicy` inspects which evaluator failed to decide between `401 Unauthorized` (authentication failure) and `403 Forbidden` (authorization failure). Any evaluator tagged as `AuthorizationAccessEvaluator` produces a 403; everything else produces a 401.

### 9.4 Role-Based Authorization

The authorization model has three dimensions:

- **Resource** — the name of the entity or endpoint being accessed (matched case-insensitively).
- **Action** — `read`, `create`, `update`, `delete`, or the wildcard `any`.
- **Scope** — `all` (access to every record) or `own` (access only to records owned by the user).

`AuthorizationService::isAuthorized()` resolves permissions in three layers, from fastest to slowest:

1. **Token scopes** — if the JWT's `authz` array already contains `resource:action` or `resource:any`, access is granted immediately.
2. **In-request cache** — the resolved authorization set for this user is stored in memory for the duration of the current request.
3. **Database** — `AuthorizationResolver` fetches `Authorization` objects whose `name` matches the resource, whose `type` matches the action or `any`, and whose `roles` overlap with the user's roles. Results populate both cache layers.

This layering means that a warm-cache user with a valid JWT authorizing the requested scope never touches the database for authorization. The database is only consulted on the first request of a session or on a cold cache miss.

### 9.5 Authentication Endpoints

`AuthenticationManager` is itself a responder in the chain that exposes three actions:

- **`POST /login`** — validates credentials, then either issues a JWT (if `JWTPrivateKey` is set) or starts a session with a regenerated ID (to prevent session fixation). Returns the user object and, in JWT mode, the token.
- **`POST /logout`** — in session mode, destroys the session. In JWT mode, increments the user's `refreshTokenVersion`, which immediately invalidates all outstanding tokens for that user.
- **`POST /refresh`** — issues a new JWT from a valid refresh-scoped token. The issued token carries fresh expiry and scope information.

---

## 10. Rate Limiting and Idempotency

### Rate Limiting

Rate limiting is enforced before the first responder is consulted, and therefore before any credential is verified. That ordering is deliberate: verifying costs a Core Data lookup and a bcrypt comparison, and a limiter that waits for them lets an unauthenticated caller spend both on every request. It also means the username reaching this step is the one the request *claims*, which cannot be what the counter rests on — keyed on the name alone, anyone could exhaust another subject's quota by asserting their username, or escape the address limit entirely by inventing a new name per request.

Every key therefore carries `REMOTE_ADDR`, the real TCP peer, which no request header can forge:

- `rate_limit:ip:<address>` is incremented for every request, and is what bounds the cost of the work still ahead.
- `rate_limit:ip:<address>:user:<claimed>` is incremented as well when the request names a subject, granting it the larger `RATE_LIMIT_MAX_REQUESTS_USER` allowance within that address.

A request that names a subject is held to the user allowance on both counters, so a legitimate client is not tied to the anonymous limit, while one rotating usernames gains nothing — the address counter is the same one either way. Clients sharing an egress address (behind a NAT or forward proxy) share the address counter. The reported quota describes whichever counter is closer to being spent, since that is the one the client will hit. The counter and TTL are stored in a configurable backend.

Available backends: APCu (default, shared memory within a single server), Redis, Memcached, and InMemory (process-scoped, for testing). The backend is configured by replacing `Application::$rateLimitStore`.

When the limit is exceeded, the framework throws `429 Too Many Requests` with a `Retry-After` header set to the remaining TTL of the current window. The `RateLimitHeaderTransformer` appends `X-RateLimit-Limit`, `X-RateLimit-Remaining`, and `X-RateLimit-Reset` to every response so clients can track their quota without waiting for a rejection.

### Idempotency

For `POST` and `PATCH` requests, the framework supports the `Idempotency-Key` header. The key is combined with the HTTP method, URL path, and user identity to form a composite cache key.

If the same key arrives while the first request is still processing, the framework returns `409 Conflict`. If the first request has completed, the cached response is replayed directly through the infrastructure transformer pipeline (so it still gets up-to-date security and rate-limit headers) without re-executing the action.

The same four backends (APCu, Redis, Memcached, InMemory) are available for idempotency storage.

---

## 11. CORS and Security Headers

### CORS

`CORSPolicy` is a value object built from environment variables at startup. It carries allowed origins, methods, headers, the `allowCredentials` flag, and the set of exposed headers.

Each responder narrows the global policy to its own capabilities: the effective allowed methods and headers are the intersection of the application-wide policy and the responder's declared `$allowedMethods` and `$allowedHeaders`. This means the `Access-Control-Allow-Methods` header on `/login` only lists the methods `AuthenticationManager` actually handles, not everything the application supports.

`CORSResponseTransformer` writes the `Access-Control-*` headers on every response for which the request's `Origin` is in the allowed set. Preflight (`OPTIONS`) requests are handled before the main pipeline even starts, by `PreflightResponder`.

### Security Headers

`SecurityHeadersPolicy` is another environment-driven value object. It holds values for six headers. Four have non-null defaults:

| Header                   | Default              |
|--------------------------|----------------------|
| `X-Content-Type-Options` | `nosniff`            |
| `X-Frame-Options`        | `SAMEORIGIN`         |
| `Referrer-Policy`        | configurable default |
| `Permissions-Policy`     | configurable default |

`Content-Security-Policy` and `Strict-Transport-Security` are `null` by default and must be configured explicitly — their values are application-specific. `SecurityHeadersTransformer` omits any header whose policy value is null, so partial configurations are valid.

The policy can be replaced programmatically in the application delegate:

```php
Application::shared()->securityHeadersPolicy = new SecurityHeadersPolicy(
    contentSecurityPolicy: "default-src 'self'",
    strictTransportSecurity: "max-age=31536000; includeSubDomains"
);
```

---

## 12. MCP Server

The MCP (Model Context Protocol) server exposes the application's data model as a JSON-RPC 2.0 tool catalogue that LLM agents can discover and invoke. It is available at `/mcp` and accepts `GET`, `POST` and `DELETE` — the three verbs the Streamable HTTP transport uses, `DELETE` being how a client ends the session its handshake established.

### Protocol

Every request is parsed as a JSON-RPC 2.0 message. Notifications (messages without an `id` field) are silently ignored. The response is always wrapped in the standard envelope `{"jsonrpc":"2.0","id":...,"result":...,"error":...}`. Any uncaught exception inside the handler is converted to a `JSONRPCError` with code `−32603 Internal Error`; the MCP server never leaks an HTTP 500.

### Transport

`MCPTransportGuard` enforces the Streamable HTTP transport requirements before a message is dispatched. These rules are about *where* a request comes from and *whether the handshake happened* — never about who the caller is, which stays with the access evaluator chain. Three checks, each rejecting with the status the specification prescribes:

- **Origin** — a request carrying an `Origin` outside `MCP_ALLOWED_ORIGINS` is refused. A request with no `Origin` header passes: MCP clients are not browsers and send none, so the header only appears for a page running in the user's browser, which is the DNS-rebinding vector the check exists to close.
- **Session** — any method other than `initialize` must carry the `Mcp-Session-Id` the server issued during the handshake. Missing is `400`; unknown or expired is `404`, which tells the client to initialize again rather than to give up. The session does not authenticate — the specification forbids that — and is stored under a key that includes the token's subject, so an identifier guessed by one identity does not resolve under another. `MCPSessionStore` backs it with APCu, Redis or memory, and its idle lifetime (`MCP_SESSION_TTL`, default 3600s) is refreshed on every request that carries it.
- **Protocol version** — an unsupported `MCP-Protocol-Version` is `400`. An absent header means the legacy version, as the specification requires for backwards compatibility.

Independently of the guard, `JSONWebTokenAudienceEvaluator` in the access chain binds a token to this endpoint when `MCP_TOKEN_AUDIENCE` is set: only a bearer token carrying that audience reaches `/mcp`, so the credential a user receives by signing in to the application does not open MCP as a side effect. Note that the framework's own `/login` and `/refresh` issue tokens without an `aud` claim, so setting this variable closes `/mcp` to them — it is for deployments that mint MCP tokens through `JSONWebTokenIssuer::issue()` with an explicit audience.

Five methods are dispatched:

| Method                      | Handler             | Purpose                                                                        |
|-----------------------------|---------------------|--------------------------------------------------------------------------------|
| `initialize`                | `InitializeHandler` | Returns server name, version, protocol version, capabilities, and instructions |
| `tools/list`                | `ToolsListHandler`  | Returns the catalogue of available tools with their input schemas              |
| `tools/call`                | `ToolsCallHandler`  | Invokes a tool by name with supplied arguments                                 |
| `ping`                      | —                   | No-op; acknowledged without a result                                           |
| `notifications/initialized` | —                   | No-op                                                                          |

### Schema

On first access, `ModelDescriptor` builds a `ModelSchema` from the Core Data model:

- **Entities** — every entity in the model is described with its PHP class name, a human-readable label, optional aliases, its attributes (name, type, nullability, enum cases where applicable), and its relationships (target entity, to-one or to-many, optional or required).
- **Predicate syntax guide** — a reference section embedded in the schema that teaches the LLM the format string syntax (`%K` for key paths, `%s`/`%d`/`%f` for values), the available operators, and localised examples.

Two files enrich the raw schema:

- **`mcp_vocabulary.json`** (localised) — adds human-readable descriptions and aliases to entities and attributes. Attribute descriptions use the key `Entity.attributeName`.
- **`mcp_predicate_examples.json`** (localised) — a list of example predicate strings included verbatim in the schema to guide the LLM. The framework ships none: the file is supplied by the application, resolved through `Bundle::main()`.

**Enum detection** deserves special mention: `AttributeSchemaFactory` inspects the managed object class for a method named `validate{AttributeName}`. If such a method exists and its parameter type is a `BackedEnum`, the factory extracts all case names and values and includes them in the attribute schema. This lets the LLM know the valid values for enum fields without a database query.

### Built-in Tools

The framework registers twelve tools automatically — nine of them backed by the managed object context, `run_job` by the domain job catalogue, and `get_server_time` and `web_search` by the framework itself:

| Tool             | Operation                                                   | Notes                                                                          |
|------------------|-------------------------------------------------------------|--------------------------------------------------------------------------------|
| `describe_model` | Schema introspection                                        | Should be called first. With no argument returns a lightweight index of every entity (class, label, aliases, attribute/relationship counts) plus the predicate guide; pass `entity` (one name or an array of names) for the full attributes, relationships and enum cases of those entities |
| `fetch`          | Query with filters, sort, pagination, projection            | Field/relationship projection; `serialization` shape traverses relationships to any depth; no default limit — `limit` is passed through as given, and omitting it fetches every matching row |
| `count`          | Count matching records                                      |                                                                                |
| `aggregate`      | Compute sum, average, min, max, count, median, mode, stddev | `median`, `mode`, `stddev` are computed in-memory; others push to the database |
| `group_by`       | GROUP BY with aggregates, HAVING, sort, pagination          | Fully database-side                                                            |
| `create`         | Insert a single record                                      | Returns the created object                                                     |
| `update`         | Update a single record by `objectID`                        | Saves only if there are actual changes                                         |
| `delete`         | Delete a single record by `objectID`                        |                                                                                |
| `persistent_history` | Fetch or purge the persistent history change log        | Mirrors the `/history` endpoint; purge is destructive                          |
| `run_job`         | Run a domain job by name                                 | Backed by the `src/Jobs/` catalogue, not the data model; same transaction boundary as the CRUD tools; gate on the `Jobs` resource |
| `get_server_time` | Return the server's current date and time                | App-agnostic; reads no entities, so no security scope                           |
| `web_search`      | Search the web via a pluggable provider                 | Backed by `src/Search/` (`WebSearchProvider`, `TavilySearchProvider`); the application picks the provider by identifier in its `.env` (`WEB_SEARCH_PROVIDER`). Note the `LLMClient` backing an agent is *not* environment-driven — it is constructed and injected in code |

Every tool validates all key paths and predicate placeholders against the in-memory schema before touching the database, so invalid field names produce a clear error message rather than a SQL error.

The full per-tool reference — every input parameter and the security behaviour of each tool — is in [MCP.md](MCP.md).

### Custom Tools

Place a class that extends `AbstractTool` in `src/MCPTools/`. The framework discovers it automatically at startup. The class receives the `ManagedObjectContext` and `ModelDescriptor` in its constructor and has access to all helper methods from `AbstractTool`: query-building (`fetchRequest`, `buildPredicate`), validation (`validateKeyPath`, `validatePredicateKeyPaths`, `assertConcreteEntity`), and result-wrapping (`jsonResult`, `textResult`).

A custom tool must also apply the same security helpers the built-in tools use — the MCP request URL is always `/mcp`, so none of the URL-driven guards that protect a regular endpoint apply, and a tool that skips them reads or writes rows the caller is not entitled to:

- **`applySecurityScope($request)`** — call on every `FetchRequest` the tool builds, before executing it. AND-folds in the caller's `own` ownership scope and the resource-level `#[Readable]`.
- **`enforceFieldRead($entityName, $keyPath)`** — call on every key path an aggregate computes over or groups by. `applySecurityScope` narrows which rows are read; this narrows which columns.
- **`enforceResourceAccess($object)`** — call on every object the tool creates, updates or deletes (on create, after populating it). Enforces the resource-level `#[Writable]`; throws `ForbiddenException` on denial.
- **`enforceEntityAuthorization($resource, $action)`** — call to check per-entity RBAC for the resource, the check `AuthorizationEvaluator` performs by URL for regular endpoints.
- **`applySecureRead` / `applySecureUpdate` / `enforceOwnership`** — field-level read filtering, field-level write filtering, and `#[Owner]` enforcement, respectively.

### In-process agents and subagents

The JSON-RPC server is one face of the tool catalogue. `LLMAgent` drives the same catalogue in-process: it loops over `LLMClient` turns, dispatches tool calls through a `ToolRegistry`, and feeds results back until the model finishes or the iteration budget runs out. Agents built on it expose one synthetic tool the registry never sees: **`run_subagent`** — launch a fresh agent on the same catalogue to complete one bounded task. The subagent is a new `LLMAgent` bound to the same client and registry, so every data tool still enforces its own RBAC; the recursion guard is structural (`canSpawnSubagents: false`), not a prompt. The subagent's final answer is returned as the tool result, and its tokens are charged to the parent run.

---

## 13. Event Streaming

Server-Sent Events are how the framework holds a long-lived connection open, pushing real-time updates to browser clients without WebSockets. An application streams them from any responder by returning an `EventStreamResponse`.

`EventStreamResponder` is the framework's own example of that, marked `@internal` and carrying `#[Endpoint("Events")]`. It is **not** part of the built-in responder chain, so `/Events` answers nothing unless an application places a responder of its own there.

Responses are produced by `EventStreamResponse`, which streams `ServerSentEvent` objects through an `EventStreamEmitter`. Each event carries a data payload, an optional ID and an optional event type.

The `StreamResponse` class provides a more general incremental streaming mechanism: it wraps any iterable result set and serialises records in configurable chunks, applying an optional transform function to each chunk. `PersistentSpace` uses this automatically when a fetch request specifies a `fetchBatchSize`.

---

## 14. Server-Side Rendering

`ViewController` extends `Responder` with a view lifecycle for server-side HTML rendering.

The lifecycle proceeds as follows:

1. `viewWillLoad()` is called — override to set data before the template context is assembled.
2. The framework collects every public property annotated with `#[Outlet]` and builds the template context from their current values.
3. A `View` object is instantiated with the template name, the context dictionary, and the configured `Renderer`.
4. `viewDidLoad()` is called — override for any post-render setup.
5. `View::render()` produces the HTML string, which becomes `$this->data`.

The renderer class is a static property on `ViewController` and can be replaced globally:

```php
ViewController::$rendererClass = LatteRenderer::class;
```

The default renderer uses PHP's native `include` mechanism. Any renderer that implements the `Renderer` interface can be substituted.

Templates are resolved from the `Renderer`'s associated `Bundle`. `HomeController` uses the framework's own bundle; custom `ViewController` subclasses use `Bundle::main()` by default, or a custom bundle if `$bundle` is overridden.

---

## 15. Scheduled Jobs

The request pipeline is not the only way into the framework. Recurring maintenance (cron) and one-shot provisioning run through a separate CLI entry point that shares the same Core Data stack and application delegate, but never the HTTP responder chain.

### The Job base class

A job is a `final` class that extends `Sabatier\Service\Jobs\Job` and holds business logic only:

```php
final class ResetMachineAvailability extends Job
{
    public function run(ManagedObjectContext $context): void
    {
        // …business logic, using $this->log(...) for progress…
    }
}
```

`Job` mirrors the `AbstractTool` design used by the MCP subsystem — a base class with a `name` property hook that acts as the registry key:

- **`name`** — the lookup key the CLI matches against its argument. It is a concrete hook that defaults to the class short name (`class_name(static::class)`), so a job is invoked by its class name unless it overrides `name`. This is the job counterpart of `AbstractTool::name` and `#[Endpoint]`'s default path.
- **`run(ManagedObjectContext $context): void`** — the only abstract member. The context arrives already configured (transaction author, merge policy); the job must **not** `save()` or `reset()` it — that is the entry point's responsibility, exactly as an HTTP responder never manages the transaction boundary itself.
- **`log(string $message): void`** — a concrete `protected` helper that writes a progress line through `error_log` (the channel a cron redirection `>> …log 2>&1` captures), prefixed with the date and the job's `name`. Override it to send progress elsewhere.

### Discovery

Jobs are discovered exactly like MCP tools and responders — by scanning the application's source tree, not a manifest. `JobResolver` scans `src/Jobs/`, reconstructs each FQCN as `App\Jobs\{FileBaseName}`, and keeps every instantiable subclass of `Job`. `JobRegistry` reduces the resolved list into a `Dictionary<Job>` keyed by `$job->name`, and looks a job up by name (`job(string): ?Job`), exposing `$names` for the CLI usage message. There is no registration step: dropping a `Job` subclass in `src/Jobs/` makes it runnable.

Because the CLI matches its argument against the registry's keys and never instantiates a class from raw input, an argument that no discovered job declares simply cannot run.

### The CLI run loop

`JobRunner::run()` is the command-line run loop — the counterpart to `Application::run()`. Where `Application::run()` is the HTTP entry point (boot the framework, answer a request, `never` return), `JobRunner::run()` is the CLI entry point (boot the same framework by hand, run the named job, `never` return — it cannot call `Application::run()`, which is HTTP-only). A project's `cli.php` is therefore as thin as its `index.php`:

```php
<?php

declare(strict_types=1);

require_once __DIR__ . "/vendor/autoload.php";

use Sabatier\Service\Jobs\JobRunner;

new JobRunner()->run();
```

`run()` performs the sequence the HTTP path performs inside `run()`:

1. Resolve the job named by the first command-line argument (`ProcessInfo`) against the registry — `new JobRegistry(new JobResolver()->resolve())`. A missing or unknown name prints the usage with the available job names to `STDERR` and exits `1`.
2. Boot the delegate in order — the class-level `initialize()` (declared on `ObjectClass`, which every generated delegate extends), then `applicationWillFinishLaunching()` — the same hooks the HTTP path fires, registering UserDefaults flags and the Core Data stores.
3. Read `persistentContainer->viewContext` and set its `transactionAuthor` (the `JobRunner` constructor's argument, default `"system"`), so persistent history records who performed the writes.
4. Run the job inside `try/catch (Throwable)`, `save()` on success only if the context `hasChanges`, `reset()` in `finally`, and `exit` with `0` on success or `1` on failure.

The run loop owns the orchestration lines (`started`, `completed in …`, `failed: …`) and writes them with the same `[date] [name]` prefix that `Job::log` uses for a job's internal progress, so both streams read uniformly in the log. A failing job is logged before the non-zero exit, so cron captures the cause.

### Recurring vs. one-shot

Not every job is scheduled. One-shot provisioning jobs (run manually once per environment, never in `crontab`) share the same `src/Jobs/` + CLI machinery as recurring cron jobs; the only difference is whether a `crontab` line invokes them. The framework draws no distinction — both are just `Job` subclasses.

### The MCP surface

The same catalogue is exposed to LLM agents as the `run_job` MCP tool (documented in [MCP.md](MCP.md)): it resolves the name against the same registry, runs the job against the request context, and saves in the same transaction boundary the CRUD tools keep. Because running a job is a coarse action that may read or write, the tool gates each call on a `Jobs` permission of type `any`. The CLI and MCP paths therefore stay in lockstep — a job dropped in `src/Jobs/` is runnable from both entry points with no extra registration.

---

## 16. Application Delegate

`ApplicationDelegate` is the primary customization point. The framework discovers the delegate by reading the `NSPrincipalClass` key from the main bundle's `Info.plist` and verifying that the class implements `ApplicationDelegate`.

The delegate receives four lifecycle callbacks:

- **`applicationWillFinishLaunching`** — called after the Core Data stack is loaded but before any request processing begins. The right place to configure policies, override `$rendererClass`, or perform one-time setup.
- **`applicationDidFinishLaunching`** — called just before the response is sent. Useful for post-response hooks.
- **`applicationWillTerminate`** — called by the PHP shutdown handler on a clean exit.
- **`applicationDidCrash`** — called when a fatal PHP error is caught by the shutdown handler, before the error response is emitted.

Application-wide policies — `$corsPolicy`, `$accessPolicy`, `$securityHeadersPolicy`, `$cachePolicy`, `$rateLimitPolicy`, `$idempotencyPolicy`, `$staticResourcePolicy`, `$fileTransferPolicy`, `$rateLimitStore`, `$idempotencyStore`, `$authorizationCache` — are public properties on `Application` and can be reassigned in `applicationWillFinishLaunching` to replace any default.

---

## 17. Environment Configuration Reference

The tables below name the **PHP constants** the framework declares for each setting, which is what source code refers to. They are not the strings an operator writes in a `.env` — `JWTValidityTimeIntervalKey` is the constant, `JWT_VALIDITY_TIME_INTERVAL` the variable — and they cover only the groups worth explaining here.

**[.env.example](.env.example) is the complete reference**, listing every variable the framework reads under its real name, with its default and what it does. Reach for it when configuring a deployment; reach for these tables when reading the framework's own code.

### Authentication and JWT

| Key                          | Default | Description                                                                               |
|------------------------------|---------|-------------------------------------------------------------------------------------------|
| `JWTPrivateKey`              | —       | Enables JWT mode when set. Value is the signing key (HMAC secret or RSA private key PEM). |
| `JWTSignatureAlgorithmKey`   | `HS256` | Signing algorithm. Values: `HS256`, `RS256` — the two coder strategies the framework registers. |
| `JWTValidityTimeIntervalKey` | 1800    | Token lifetime in seconds.                                                                  |
| `JWTIssuerEnvironmentKey`    | —       | Canonical `iss` claim, used for both issuance and validation. When unset, issuer validation is skipped. Never derived from the request host. |

### CORS

| Key                       | Description                                           |
|---------------------------|-------------------------------------------------------|
| `CORSAllowedOriginsKey`   | Comma-separated list of allowed origins, or `*`.      |
| `CORSAllowedMethodsKey`   | Comma-separated list of allowed HTTP methods.         |
| `CORSAllowedHeadersKey`   | Comma-separated list of allowed request headers.      |
| `CORSAllowCredentialsKey` | `true` / `false`.                                     |
| `CORSExposedHeadersKey`   | Comma-separated list of headers the browser may read. |

### Security Headers

| Key                                  | Default           | Description                             |
|--------------------------------------|-------------------|-----------------------------------------|
| `SECURITY_X_CONTENT_TYPE_OPTIONS`    | `nosniff`         |                                         |
| `SECURITY_X_FRAME_OPTIONS`           | `SAMEORIGIN`      |                                         |
| `SECURITY_REFERRER_POLICY`           | `strict-origin-when-cross-origin` |                         |
| `SECURITY_PERMISSIONS_POLICY`        | `camera=(), microphone=(), geolocation=()` |                |
| `SECURITY_CSP`                       | —                 | Not set by default; must be configured. |
| `SECURITY_HSTS`                      | —                 | Not set by default; only meaningful over HTTPS. |

### Rate Limiting

| Key                           | Description                                                |
|-------------------------------|------------------------------------------------------------|
| `RateLimitEnabledKey`         | Enable or disable rate limiting.                           |
| `RateLimitMaxRequestsIPKey`   | Request quota for anonymous clients per window, keyed on `REMOTE_ADDR`. |
| `RateLimitMaxRequestsUserKey` | Request quota for authenticated users per window.          |
| `RateLimitWindowSecondsKey`   | Window duration in seconds.                                |

### MCP

| Key                               | Default                   | Description                              |
|-----------------------------------|---------------------------|------------------------------------------|
| `MCPServerNameKey`                | bundle name               | Server name reported in `initialize`.    |
| `MCPServerVersionKey`             | bundle version            | Server version reported in `initialize`. |
| `MCPInstructionsFilenameKey`      | `mcp_instructions.txt`    | Localised instructions file for the LLM. |
| `MCPVocabularyFilenameKey`        | `mcp_vocabulary.json`     | Localised schema vocabulary file.        |
| `MCPPredicateExamplesFilenameKey` | `mcp_predicate_examples.json` | Localised predicate examples file, supplied by the application. |

### Application

| Key                         | Description                                             |
|-----------------------------|---------------------------------------------------------|
| `ApplicationEnvironmentKey` | Set to `development` to enable verbose error responses. |
