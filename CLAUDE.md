# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Code Quality Commands

The dev tools are installed **globally** (Composer global, on `PATH`) — `vendor/bin` is empty. Invoke them by bare name, not via `vendor/bin/`:

```bash
# Tests
phpunit                       # full suite (config: phpunit.xml)

# Static analysis
phpstan analyse
psalm --show-info=false

# Code style check and fix
php-cs-fixer fix --dry-run    # check
php-cs-fixer fix              # apply

# Automated refactoring
rector --dry-run              # check
rector                        # apply

# CodeSniffer
phpcs src/
```

The framework has a PHPUnit suite under `tests/` (`tests/Unit`, `tests/Integration`). Two tests fail in a clean tree, unrelated to any current work — `AuthorizationServiceTest::tokenScopeExactMatchGrantsAccessWithoutCacheLookup` (a fatal "Premature end of PHP process" that aborts the whole-suite run) and `FieldSecurityFilterTest::filterReadRemovesOwnedFieldWhenNotOwner`. Run suites per directory (`phpunit tests/Integration`) to work around the crash.

## Architecture

`sabatier/service` is an application framework for PHP 8.5+ built on two sibling libraries (`sabatier/foundation` and `sabatier/coredata`). Most source files live flat in `src/`. The exception is `src/MCP/`, which is a self-contained subsystem with its own subdirectories (`Tools/`, `Response/`, `Schema/`).

### The `$data` pattern

A responder never constructs a `Response` directly in the normal case. It **provides data** and lets the framework build the response from it.

- **GET responders** — override the `$data` property hook. The framework reads it once, lazily, and passes the result through the transformer chain declared on `#[Endpoint]`.
- **Action responders** — define `#[Action]` methods that perform their work and assign `$this->data = ...` before returning. The framework then passes `$this->data` through the transformer chain declared on `#[Action]`.
- **Override `$response` only** when you need full control: custom status codes with no body, streaming responses (`StreamResponse`), or PersistentSpace-level behavior.

Do **not** introduce a `$response` override when overriding `$data` is sufficient.

### Responder Chain

`Application` is the singleton root of the responder chain (`Application::shared()`). Every request is routed through `FirstResponderResolver`, which maps the incoming URL to a `Responder` subclass via `#[Endpoint]` and `#[Action]` attributes:

- **`#[Endpoint("/path", transformers: [...])]`** — marks a class as serving GET; the class-level route.
- **`#[Action(method: HTTPRequestMethod::patch, transformers: [...])]`** — marks a method as handling a mutation (POST/PATCH/DELETE).
- **`#[Outlet]`** — on `ViewController` properties; reflects data into view templates automatically.

`ViewController` extends `Responder` and adds a view rendering lifecycle (`viewWillLoad` / `viewDidLoad`).

Custom responders are **auto-discovered** from `src/Responders/` and `src/ViewControllers/` and prepended to the chain ahead of built-in responders. Custom MCP tools are auto-discovered from `src/MCPTools/`.

### PersistentSpace

`PersistentSpace` handles all standard CRUD for Core Data entities with no custom responder needed. It activates (`$isFirstResponder = true`) when the URL's last path component matches a registered entity name. It handles `GET`, `POST`, `PATCH`, and `DELETE` automatically, including field-level security and ownership scoping.

Do **not** create a custom responder for an entity just to expose basic CRUD — PersistentSpace already does it.

For `PATCH` and `DELETE`, the request must provide `objectID` (the `ManagedObjectObjectIDKey` constant) — in the body, or in the query string for keys the body omits (the body is authoritative on collision). For `GET`, query parameters become equality predicates; or pass a full `FetchRequest` as a base64-encoded JSON via `?fetchRequest=`.

### PersistentHistoryResponder

`PersistentHistoryResponder` is a built-in responder registered at `/history`. It exposes the Core Data persistent history API over HTTP and is always active — no custom responder needed.

It is only meaningful when persistent history tracking is enabled for the store. That option is controlled by `PersistentHistoryTrackingKey` in `UserDefaults` and applied automatically by `Application` when configuring `PersistentStoreDescriptions`.

**Allowed methods:** `GET` and `DELETE`.

#### GET — fetch history

Returns a JSON body with the history result. The result set is scoped by one of three optional query parameters; if none is provided, all history is returned.

| Parameter          | Type        | Description                                                                                    |
|--------------------|-------------|------------------------------------------------------------------------------------------------|
| `afterDate`        | date string | Transactions after this date                                                                   |
| `afterTransaction` | int         | Transactions after this transaction number                                                     |
| `afterToken`       | base64 JSON | Transactions after the given token                                                             |
| `resultType`       | int         | `PersistentHistoryResultType` raw value                                                        |
| `fetchRequest`     | base64 JSON | Filter by `PersistentHistoryTransaction` properties (e.g. `author`, `contextName`, `bundleID`) |

#### DELETE — purge history

Removes history from the store. Responds with `204 No Content`. Scoped by one optional parameter; if none is provided, all history is purged.

| Parameter           | Type        | Description                                                                                                               |
|---------------------|-------------|---------------------------------------------------------------------------------------------------------------------------|
| `beforeDate`        | date string | Purge transactions before this date                                                                                       |
| `beforeTransaction` | int         | Purge transactions before this number                                                                                     |
| `beforeToken`       | base64 JSON | Purge transactions before the given token                                                                                 |
| `fetchRequest`      | base64 JSON | Scope which transactions to purge by `PersistentHistoryTransaction` properties (e.g. `author`, `contextName`, `bundleID`) |

Tokens are passed as base64-encoded JSON, decoded via `PersistentHistoryToken`. The adapter (`PersistentHistoryChangeRequestAdapter`) is `@internal` and translates request parameters into a `PersistentHistoryChangeRequest` before execution.

### Response Pipeline

Every response goes through two pipelines in sequence:

1. **User pipeline** — the transformers declared on `#[Endpoint]` or `#[Action]` (e.g. `JSONTransformer`, `NoCacheHeaderTransformer`). Determined per-responder.
2. **Infrastructure pipeline** — always runs, regardless of the responder: `CacheHeaderTransformer`, `ConditionalGetTransformer`, `RateLimitHeaderTransformer`, `SecurityHeadersTransformer`, `CORSResponseTransformer`.

Because `SecurityHeadersTransformer` is part of the infrastructure pipeline, it runs on **every** response automatically — including `ErrorResponder`, `EventStreamResponder`, and `PersistentSpace`. You do not need to add it to your own transformer list.

Key user-pipeline transformers:

- `JSONTransformer` / `HTMLTransformer` — serialization
- `NoCacheHeaderTransformer` — disables caching for the user pipeline leg
- `DownloadResponseTransformer` — sets `Content-Disposition: attachment`
- `ResponseHeaderSanitizerTransformer` — strips internal headers before sending

`ResponseTransformerContext` carries the request and all active policies into the transformer chain.

### Policies

All policies are configured on `Application` and inherited by each `Responder` via lazy property hooks:

| Property                 | Type                    | Purpose                                                                    |
|--------------------------|-------------------------|----------------------------------------------------------------------------|
| `$corsPolicy`            | `CORSPolicy`            | Allowed origins, methods, headers                                          |
| `$accessPolicy`          | `AccessPolicy`          | Access control; override with `DefaultAccessPolicy` / `PublicAccessPolicy` |
| `$securityHeadersPolicy` | `SecurityHeadersPolicy` | Security response headers                                                  |
| `$cachePolicy`           | `HTTPCachePolicy`       | ETag generation, Cache-Control defaults                                    |
| `$rateLimitPolicy`       | `RateLimitPolicy`       | Rate limiting; backends: APCu, Redis, Memcached, InMemory                  |
| `$idempotencyPolicy`     | `IdempotencyPolicy`     | Replay protection for POST/PATCH; same backends                            |

### Security & Auth

The framework auto-selects its auth mode from the environment:
- **JWT mode** — when `JWT_PRIVATE_KEY` env var is set; stateless, uses `JSONWebTokenService`.
- **Session fallback** — default for stateful/browser apps.

Authentication strategies: `BasicAuthentication`, `BearerAuthentication`, `DigestAuthentication`.
JWT codec strategies: `JSONWebTokenHS256EncoderStrategy`, `JSONWebTokenRS256DecoderStrategy` (and HS256/RS256 variants).

Access control flows through `AccessEvaluatorChain` → `AccessPolicy` → `AuthorizationService` → `AuthorizationCache`.

The default evaluator chain (AND short-circuit) is:
`SessionAuthenticationEvaluator` → `AuthenticationEvaluator` → `JSONWebTokenScopeEvaluator` → `JSONWebTokenAccessTimeEvaluator` → `JSONWebTokenEnabledEvaluator` → `JSONWebTokenVersionEvaluator` → `AuthorizationEvaluator`

The `/refresh` action uses a shorter chain that accepts only the refresh-scoped token and skips `AuthorizationEvaluator`.

Field-level security is declared with `#[Readable]` and `#[Writable]` attributes on managed object properties. `FieldSecurityFilter` applies them at read and write time with a static reflection cache. `#[Owner]` marks the ownership field; `OwnershipService` enforces it on PATCH and DELETE.

### Property Hooks

The codebase uses PHP 8.5 property hooks throughout for lazy initialization:

```php
private(set) SomeService $service {
    get => $this->service ??= new SomeService(...);
}
```

Do **not** convert these to constructor injection or traditional getters — this is a deliberate architectural pattern.

### Abstract bases requiring annotations

Abstract classes used as polymporphic bases (`ResponseTransformer`, etc.) must carry both:
```php
/** @psalm-consistent-constructor */
/** @phpstan-consistent-constructor */
```

## Code Style Rules

- **No `?? null` or `?? ""` with `Dictionary`** — `Dictionary::offsetGet` already returns `null` for missing keys. Read keys directly: `$dict["key"]` or `(string)$dict["key"]`.
- **Short array syntax** — always `[]`, never `array()`.
- **PSR-12** with strict params (`declare(strict_types=1)` in all files).
- **Transformer chains** — compact style, minimal line breaks; match the surrounding code.
- **No constructor property promotion** — skipped in Rector config by design.
- **No `readonly` property conversion** — also skipped in Rector.
- **Double quotes** — always use `"string"`, never `'string'`. Prefer interpolation over concatenation: `"$var:"` instead of `$var . ":"`. Use curly braces only when necessary (e.g. `"{$obj->prop}"`, `"{$arr['key']}"`)
