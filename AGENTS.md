# AGENTS.md

## Quick facts

- **No test suite** — framework library, no tests to run or discover.
- 173 source files live flat in `src/` — no subdirectories.
- Requires PHP 8.5+. Property hooks (`private(set) Type $prop { get => ... }`) are used throughout for lazy initialization — never convert to constructor injection or traditional getters.
- Sibling libraries `sabatier/foundation` and `sabatier/coredata` are loaded via composer path repos (`../Foundation`, `../CoreData`).

## Code quality commands

```bash
vendor/bin/phpstan analyse
vendor/bin/psalm
vendor/bin/php-cs-fixer fix --dry-run   # check
vendor/bin/php-cs-fixer fix             # apply
vendor/bin/rector --dry-run             # check
vendor/bin/rector                       # apply
vendor/bin/phpcs src/
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
- **`SecurityHeadersTransformer`** must be included on *all* responders — `ErrorResponder`, `EventStreamResponder`, `PersistentSpace`, not just base `Responder`.

## Architecture entrypoints

- `Application::shared()` — singleton root of the responder chain.
- `FirstResponderResolver` — routes requests via `#[Endpoint]` (GET) and `#[Action]` (POST/PATCH/DELETE) attributes.
- Policies (`$corsPolicy`, `$accessPolicy`, `$securityHeadersPolicy`, `$cachePolicy`, `$rateLimitPolicy`, `$idempotencyPolicy`) are set on `Application` and inherited via property hooks.

## Auth mode

Auto-selected from environment:
- **JWT mode** — when `JWTPrivateKey` env var is set (stateless).
- **Session fallback** — default for stateful/browser apps.
