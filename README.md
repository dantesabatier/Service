# Sabatier Service

**Service** is a PHP 8.5+ application framework built on two sibling libraries — [Foundation](../Foundation) and [CoreData](../CoreData) — that brings the architectural patterns of Apple's AppKit and Core Data to server-side PHP development.

It covers the full spectrum from zero-boilerplate REST APIs to server-rendered web applications, with a consistent request pipeline, layered security, and a built-in MCP server for LLM tool access — all without routing tables, code generation, or CLI scaffolding.

---

## What makes it different

**The model is the API.** Define your data model with Core Data entities, and Service automatically exposes a fully functional, secured REST endpoint for each one. No controller, no serializer, no route to register.

**Security is structural, not optional.** Authentication, authorization, field-level read/write control, rate limiting, idempotency, CORS, and security headers are wired into the framework's pipeline. You opt out of restrictions rather than opting in.

**The responder chain routes requests.** There are no routing tables. Every request walks a chain of `Responder` objects; the first one that recognizes the URL handles it. Custom endpoints extend `Responder` (or `ViewController` for HTML) and declare their route with a single `#[Endpoint]` attribute.

**It self-adapts to the environment.** JWT mode activates automatically when `JWTPrivateKey` is present; session-based auth is the fallback. Rate limiting, idempotency, and CORS are configured through environment variables, not code.

---

## Core capabilities

| Capability                | Description                                                                                                                                                                                 |
|---------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **PersistentSpace**       | Automatic CRUD REST API over every Core Data entity. Field-level security and ownership scoping included.                                                                                   |
| **Responder Chain**       | AppKit-style request routing. Built-in responders cover static files, uploads, downloads, preferences, and an MCP endpoint.                                                                 |
| **Security Pipeline**     | Basic, Bearer (JWT), and Digest authentication. Role-based authorization. Per-field read/write access control. Rate limiting with APCu, Redis, or Memcached backends.                       |
| **MCP Server**            | Exposes the full data model to LLM agents as JSON-RPC 2.0 tools — fetch, count, aggregate, group-by, create, update, delete, and batch operations — auto-derived from the Core Data schema. |
| **Server-Side Rendering** | `ViewController` manages a template lifecycle (`viewWillLoad` / `viewDidLoad`) with `#[Outlet]` properties reflected into the rendering context. Pluggable renderer engine.                 |
| **Event Streaming**       | First-class Server-Sent Events support via `EventStreamResponder`.                                                                                                                          |

---

## How the framework handles data

This is the design that most often needs explaining, because it inverts the typical PHP approach.

A responder does not build and return a response object. Instead, it provides **data** — and the framework builds the response from it. `$data` is the body. The transformers declared on `#[Endpoint]` or `#[Action]` determine how that body is serialised (as JSON, as HTML, as a file attachment) and what headers accompany it. The responder never needs to know about HTTP directly.

**For read requests (GET)**, override `$data`. The framework reads it once, lazily, and passes the result through the transformer chain:

```php
#[Endpoint("Preferences", transformers: [JSONTransformer::class, NoCacheHeaderTransformer::class])]
final class Preferences extends Responder
{
    protected mixed $data {
        get => $this->data ??= UserDefaults::standard()->dictionaryRepresentation();
    }
    // ...
}
```

**For mutating requests (POST, PATCH, DELETE)**, define `#[Action]` methods. An action runs as a side effect — it does its work and assigns `$this->data` before returning. The framework then passes `$data` through the action's own transformer chain:

```php
#[Action(transformers: [JSONTransformer::class, NoCacheHeaderTransformer::class])]
public function login(): void
{
    $user = $this->authentication->authenticatedUser ?? throw new UnauthorizedException();
    // ...
    $this->data = $data; // becomes the JSON response body
}
```

**For Core Data entities**, no responder is needed at all. `PersistentSpace` intercepts any URL whose last path component matches a registered entity name and handles `GET`, `POST`, `PATCH`, and `DELETE` automatically, including field-level security and ownership enforcement. A `User` entity in the model is immediately available as a REST endpoint at `/User` with no additional code.

Custom responders are discovered automatically from `src/Responders/` and `src/ViewControllers/` and take priority over built-in responders in the chain.

---

## Quick orientation

A minimal application needs one entry point that boots the singleton and calls `run()`:

```php
Application::shared()->run();
```

---

## Requirements

- PHP 8.5+ with extensions: `openssl`, `apcu`, `pdo`, `mbstring`, `intl`, `redis`, `memcached`
- [`sabatier/foundation`](../Foundation)
- [`sabatier/coredata`](../CoreData)

---

## License

MIT. See `LICENSE.md`.
