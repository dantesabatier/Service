# AGENTS.md

## Quick facts

- **PHPUnit suite** under `tests/` (`tests/Unit`, `tests/Integration`). The full suite passes in a clean tree — run it with `phpunit`, or per directory (`phpunit tests/Unit`, `phpunit tests/Integration`).
- Most source files live flat in `src/`. Exceptions: `src/MCP/` (subsystem with `Tools/`, `Response/`, `Schema/`) and `src/Jobs/` (the `Job` base class, `JobResolver`, `JobRegistry`).
- Requires PHP 8.5+. Property hooks (`private(set) Type $prop { get => ... }`) are used throughout for lazy initialization — never convert to constructor injection or traditional getters.
- Sibling libraries `sabatier/foundation` and `sabatier/coredata` are loaded via composer path repos (`../Foundation`, `../CoreData`).

## Code quality commands

```bash
phpunit
psalm
rector --dry-run             # check
rector                       # apply
```

## Rector constraints

Rector config (`rector.php`) skips:
- `ClassPropertyAssignToConstructorPromotionRector` — no constructor promotion by design
- `ReadOnlyPropertyRector` — no `readonly` conversion
- File-specific skips on `Application.php`, `FirstResponderResolver.php`, `MCP/Tools/AbstractTool.php`, `OwnerResolver.php`, `ResponseTransformer.php`, `MCP/InitializeHandler.php`, `MCP/ToolsListHandler.php`

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
- **Double quotes** — always `"string"`, never `'string'`. Prefer interpolation over concatenation (`"$var:"` over `$var . ":"`). Curly braces only when necessary — `"{$obj->prop}"`, `"{$arr['key']}"`.

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

Custom responders auto-discover from `src/Responders/` and `src/ViewControllers/`. Custom MCP tools from `src/MCPTools/`. Background jobs from the app's `src/Jobs/`.

## Scheduled jobs (CLI)

A parallel entry point to the responder chain, for cron and one-shot provisioning — same Core Data stack and delegate, no HTTP.

- **`Sabatier\Service\Jobs\Job`** — abstract base. `run(ManagedObjectContext $context): void` is the only abstract member; the context is pre-configured, so a job never calls `save()`/`reset()`.
- **`name`** — concrete property hook, defaults to `class_name(static::class)`; the registry key the CLI matches its argument against. Override only to decouple the key from the class name.
- **`log(string)`** — concrete `protected` helper; `error_log` with a `[date] [name]` prefix. The pipe idiom is `… |> $this->log(...)`.
- **`JobResolver`** scans the app's `src/Jobs/` (FQCN `App\Jobs\{basename}`, `is_subclass_of(Job)` + instantiable); **`JobRegistry`** keys the result `Dictionary<Job>` by `name`. No manifest, no registration step — same as `ToolResolver`, which also hardcodes the `App\` prefix. Note `FirstResponderResolver` differs: it derives the namespace from the delegate's own (`new ReflectionClass($delegate)->getNamespaceName()`), so responders work under any namespace while jobs and MCP tools must live under `App\`.
- **`JobRunner::run(): never`** — the CLI run loop, counterpart to `Application::run()`. Boots the delegate, resolves `argv[1]` against the registry, runs the job (`save` only if `hasChanges`, `reset` in `finally`), `exit`s 0/1. `transactionAuthor` is a constructor arg (default `"system"`). The app's `cli.php` is one line: `new JobRunner()->run();`.

## PersistentSpace

Handles standard CRUD for Core Data entities with no custom responder. Activates (`$isFirstResponder = true`) when the URL's last path component matches a registered entity name. Handles GET/POST/PATCH/DELETE with field-level security and ownership scoping.

For PATCH/DELETE, provide `objectID` in the body, or in the query for keys the body omits (body wins on collision). For GET, query params become equality predicates; pass `FetchRequest` as base64 JSON via `?fetchRequest=`.

## Response pipeline

Two pipelines run in sequence:
1. **User pipeline** — transformers declared on `#[Endpoint]` / `#[Action]` (per-responder).
2. **Infrastructure pipeline** — always runs: `CacheHeaderTransformer`, `ConditionalGetTransformer`, `RateLimitHeaderTransformer`, `SecurityHeadersTransformer`, `CORSResponseTransformer`.

Key user-pipeline transformers: `JSONTransformer`, `HTMLTransformer`, `NoCacheHeaderTransformer`, `DownloadResponseTransformer`, `ResponseHeaderSanitizerTransformer`.

## Policies

Configured on `Application`, inherited via lazy property hooks:

| Property                 | Type                    | Purpose                                                    |
|--------------------------|-------------------------|------------------------------------------------------------|
| `$corsPolicy`            | `CORSPolicy`            | Allowed origins, methods, headers                          |
| `$accessPolicy`          | `AccessPolicy`          | Override with `DefaultAccessPolicy` / `PublicAccessPolicy` |
| `$securityHeadersPolicy` | `SecurityHeadersPolicy` | Security response headers                                  |
| `$cachePolicy`           | `HTTPCachePolicy`       | ETag, Cache-Control defaults                               |
| `$rateLimitPolicy`       | `RateLimitPolicy`       | Backends: APCu, Redis, Memcached, InMemory                 |
| `$idempotencyPolicy`     | `IdempotencyPolicy`     | Replay protection for POST/PATCH                           |

## Auth mode

Auto-selected from environment:
- **JWT mode** — when `JWT_PRIVATE_KEY` env var is set (stateless, uses `JSONWebTokenService`).
- **Session fallback** — default for stateful/browser apps.

Authentication strategies: `BasicAuthentication`, `BearerAuthentication`, `DigestAuthentication`.
JWT codecs: `JSONWebTokenHS256EncoderStrategy`, `JSONWebTokenRS256DecoderStrategy` (and variants).

Default evaluator chain (AND short-circuit):
`SessionAuthenticationEvaluator` → `AuthenticationEvaluator` → `JSONWebTokenScopeEvaluator(access)` → `JSONWebTokenAccessTimeEvaluator` → `JSONWebTokenEnabledEvaluator` → `JSONWebTokenVersionEvaluator` → `JSONWebTokenAudienceEvaluator` → `AuthorizationEvaluator`

The `/refresh` chain is shorter and different, not a subset: `AuthenticationEvaluator` → `JSONWebTokenScopeEvaluator(refresh)` → `JSONWebTokenRefreshTimeEvaluator` → `JSONWebTokenEnabledEvaluator` → `JSONWebTokenVersionEvaluator`.

Field-level security: `#[Readable]` / `#[Writable]` on managed object properties. `#[Owner]` marks ownership; `OwnershipService` enforces on PATCH/DELETE.

## Releasing

`Info.plist` carries the released version, and nothing derives it from the git tag. When a release is cut, `CFBundleShortVersionString` becomes the tagged version (`1.0.1`, never `v1.0.1`) and `CFBundleVersion` — the build number — is incremented. `composer.json` declares no `version`: Packagist reads the tag.

`CHANGELOG.md` is written as the change is made, under `## [Unreleased]`, when a consumer would notice it — behaviour, a signature, a default, a message they read. Tagging renames that section and opens an empty one; it does not gather entries.

The full policy, and what else runs before a tag, is in [Foundation's VERSIONING.md](https://github.com/dantesabatier/Foundation/blob/master/VERSIONING.md).
