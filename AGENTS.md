# AGENTS.md

## Quick facts

- **No test suite** — framework library, no tests to run or discover.
- 173 source files live flat in `src/` — exception: `src/MCP/` has subdirectories (`Tools/`, `Response/`, `Schema/`).
- Requires PHP 8.5+. Property hooks (`private(set) Type $prop { get => ... }`) are used throughout for lazy initialization — never convert to constructor injection or traditional getters.
- Sibling libraries `sabatier/foundation` and `sabatier/coredata` are loaded via composer path repos (`../Foundation`, `../CoreData`).

## Code quality commands

```bash
phpstan analyse
psalm
php-cs-fixer fix --dry-run   # check
php-cs-fixer fix             # apply
rector --dry-run             # check
rector                       # apply
phpcs src/
```

## Rector constraints

Rector config (`rector.php`) skips:
- `ClassPropertyAssignToConstructorPromotionRector` — no constructor promotion by design
- `ReadOnlyPropertyRector` — no `readonly` conversion
- File-specific skips on `Application.php`, `FirstResponderResolver.php`, `JSONWebTokenRS256EncoderStrategy.php`, `OwnerResolver.php`, `ResponseTransformer.php`

## Patterns to preserve

- **Abstract classes** used as polymorphic bases (`ResponseTransformer`, etc.) must carry both:
  ```php
  /** @psalm-consistent-constructor */
  /** @phpstan-consistent-constructor */
  ```
- **`Dictionary` access** — `offsetGet` already returns `null` for missing keys; never write `$dict["key"] ?? null` or `?? ""`.
- **`ResponseTransformer` subclasses** perform mutations in the constructor; transformers are composed in `#[Endpoint]`/`#[Action]` attributes and applied sequentially.
- **`SecurityHeadersTransformer`** runs on *every* response via the infrastructure pipeline — no need to add it manually.
- **No `?? null` or `?? ""` with `Dictionary`** — read keys directly: `$dict["key"]` or `(string)$dict["key"]`.
- **Short array syntax** — always `[]`, never `array()`.
- **PSR-12** with `declare(strict_types=1)` in all files.
- **Transformer chains** — compact style, minimal line breaks.

## The `$data` pattern

A responder never constructs a `Response` directly. It **provides data** and lets the framework build the response:

- **GET responders** — override the `$data` property hook. The framework reads it once, lazily, through the `#[Endpoint]` transformer chain.
- **Action responders** — `#[Action]` methods assign `$this->data = ...` before returning. Passed through the `#[Action]` transformer chain.
- **Override `$response`** only for full control (custom status codes, streaming, PersistentSpace-level behavior).

## Responder chain

`Application::shared()` is the singleton root. `FirstResponderResolver` routes requests:

- **`#[Endpoint("/path", transformers: [...])]`** — GET responder; class-level route.
- **`#[Action(method: HTTPRequestMethod::patch, transformers: [...])]`** — mutation handler (POST/PATCH/DELETE).
- **`#[Outlet]`** — on `ViewController` properties; reflects data into view templates.

Custom responders auto-discover from `src/Responders/` and `src/ViewControllers/`. Custom MCP tools from `src/MCPTools/`.

## PersistentSpace

Handles standard CRUD for Core Data entities with no custom responder. Activates (`$isFirstResponder = true`) when the URL's last path component matches a registered entity name. Handles GET/POST/PATCH/DELETE with field-level security and ownership scoping.

For PATCH/DELETE, body must include `objectID`. For GET, query params become equality predicates; pass `FetchRequest` as base64 JSON via `?fetchRequest=`.

## Response pipeline

Two pipelines run in sequence:
1. **User pipeline** — transformers declared on `#[Endpoint]` / `#[Action]` (per-responder).
2. **Infrastructure pipeline** — always runs: `CacheHeaderTransformer`, `ConditionalGetTransformer`, `RateLimitHeaderTransformer`, `SecurityHeadersTransformer`, `CORSResponseTransformer`.

Key user-pipeline transformers: `JSONTransformer`, `HTMLTransformer`, `NoCacheHeaderTransformer`, `DownloadResponseTransformer`, `ResponseHeaderSanitizerTransformer`.

## Policies

Configured on `Application`, inherited via lazy property hooks:

| Property | Type | Purpose |
|---|---|---|
| `$corsPolicy` | `CORSPolicy` | Allowed origins, methods, headers |
| `$accessPolicy` | `AccessPolicy` | Override with `DefaultAccessPolicy` / `PublicAccessPolicy` |
| `$securityHeadersPolicy` | `SecurityHeadersPolicy` | Security response headers |
| `$cachePolicy` | `HTTPCachePolicy` | ETag, Cache-Control defaults |
| `$rateLimitPolicy` | `RateLimitPolicy` | Backends: APCu, Redis, Memcached, InMemory |
| `$idempotencyPolicy` | `IdempotencyPolicy` | Replay protection for POST/PATCH |

## Auth mode

Auto-selected from environment:
- **JWT mode** — when `JWT_PRIVATE_KEY` env var is set (stateless, uses `JSONWebTokenService`).
- **Session fallback** — default for stateful/browser apps.

Authentication strategies: `BasicAuthentication`, `BearerAuthentication`, `DigestAuthentication`.
JWT codecs: `JSONWebTokenHS256EncoderStrategy`, `JSONWebTokenRS256DecoderStrategy` (and variants).

Default evaluator chain (AND short-circuit):
`SessionAuthenticationEvaluator` → `AuthenticationEvaluator` → `JSONWebTokenScopeEvaluator` → `JSONWebTokenAccessTimeEvaluator` → `JSONWebTokenEnabledEvaluator` → `JSONWebTokenVersionEvaluator` → `AuthorizationEvaluator`

Field-level security: `#[Readable]` / `#[Writable]` on managed object properties. `#[Owner]` marks ownership; `OwnershipService` enforces on PATCH/DELETE.
