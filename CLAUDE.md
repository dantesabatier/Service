# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Code Quality Commands

```bash
# Static analysis
vendor/bin/phpstan analyse
vendor/bin/psalm

# Code style check and fix
vendor/bin/php-cs-fixer fix --dry-run   # check
vendor/bin/php-cs-fixer fix             # apply

# Automated refactoring
vendor/bin/rector --dry-run             # check
vendor/bin/rector                       # apply

# CodeSniffer
vendor/bin/phpcs src/
```

There are no automated tests — this is a framework library with no test suite.

## Architecture

`sabatier/service` is an application framework for PHP 8.5+ built on two sibling libraries (`sabatier/foundation` and `sabatier/coredata`). All 173 source files live flat in `src/` — no subdirectories.

### Responder Chain

`Application` is the singleton root of the responder chain (`Application::shared()`). Every request is routed through `FirstResponderResolver`, which maps the incoming URL to a `Responder` subclass via `#[Endpoint]` and `#[Action]` attributes:

- **`#[Endpoint("/path", transformers: [...])]`** — marks a class as serving GET; the class-level route.
- **`#[Action(method: HTTPRequestMethod::patch, transformers: [...])]`** — marks a method as handling a mutation (POST/PATCH/DELETE).
- **`#[Outlet]`** — on `ViewController` properties; reflects data into view templates automatically.

`ViewController` extends `Responder` and adds a view rendering lifecycle (`viewWillLoad` / `viewDidLoad`).

### Response Pipeline

Responses flow through a `ResponsePipeline` that applies `ResponseTransformer` subclasses in order. Key built-in transformers:

- `JSONTransformer` / `HTMLTransformer` — serialization
- `CORSResponseTransformer` — CORS headers
- `SecurityHeadersTransformer` — security headers (must be included on **all** responders, including `ErrorResponder`, `EventStreamResponder`, and `PersistentSpace` — not just the base `Responder`)
- `CacheHeaderTransformer` — Cache-Control / ETag
- `ConditionalGetTransformer` — ETag/Last-Modified conditional GET

`ResponseTransformerContext` carries the request and all active policies into the transformer chain.

### Policies

All policies are configured on `Application` and inherited by each `Responder` via lazy property hooks:

| Property | Type | Purpose |
|---|---|---|
| `$corsPolicy` | `CORSPolicy` | Allowed origins, methods, headers |
| `$accessPolicy` | `AccessPolicy` | Access control; override with `DefaultAccessPolicy` / `PublicAccessPolicy` |
| `$securityHeadersPolicy` | `SecurityHeadersPolicy` | Security response headers |
| `$cachePolicy` | `HTTPCachePolicy` | ETag generation, Cache-Control defaults |
| `$rateLimitPolicy` | `RateLimitPolicy` | Rate limiting; backends: APCu, Redis, Memcached, InMemory |
| `$idempotencyPolicy` | `IdempotencyPolicy` | Replay protection for POST/PATCH; same backends |

### Security & Auth

The framework auto-selects its auth mode from the environment:
- **JWT mode** — when `JWTPrivateKey` env var is set; stateless, uses `JSONWebTokenService`.
- **Session fallback** — default for stateful/browser apps.

Authentication strategies: `BasicAuthentication`, `BearerAuthentication`, `DigestAuthentication`.
JWT codec strategies: `JSONWebTokenHS256EncoderStrategy`, `JSONWebTokenRS256DecoderStrategy` (and HS256/RS256 variants).

Access control flows through `AccessEvaluatorChain` → `AccessPolicy` → `AuthorizationService` → `AuthorizationCache`.

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
