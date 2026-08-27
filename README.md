# Sabatier Service

**Service** is a PHP 8.5+ application framework built on two sibling libraries — [Foundation](https://github.com/dantesabatier/Foundation) and [CoreData](https://github.com/dantesabatier/CoreData) — that brings the architectural patterns of Apple's AppKit and Core Data to server-side PHP development.

It covers the full spectrum from zero-boilerplate REST APIs to server-rendered web applications, with a consistent request pipeline, layered security, and a built-in MCP server for LLM tool access — all without routing tables or code generation. Scheduled and one-shot background work runs through a parallel CLI entry point that shares the same Core Data stack.

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
| **MCP Server**            | Exposes the full data model to LLM agents as JSON-RPC 2.0 tools — describe-model, fetch, count, aggregate, group-by, create, update, delete, persistent-history, run-jobs, get-server-time, and web-search — the first nine auto-derived from the Core Data schema; run-jobs drives the domain job catalogue, get-server-time is an app-agnostic utility, and web-search delegates to a pluggable provider the application picks by identifier in its `.env`. |
| **Server-Side Rendering** | `ViewController` manages a template lifecycle (`viewWillLoad` / `viewDidLoad`) with `#[Outlet]` properties reflected into the rendering context. Pluggable renderer engine.                 |
| **Event Streaming**       | First-class Server-Sent Events support. A responder returns an `EventStreamResponse` of `ServerSentEvent` objects, streamed through an `EventStreamEmitter`.                                 |
| **Scheduled Jobs**        | `Job` subclasses in `src/Jobs/`, auto-discovered and keyed by a `name` hook, run through a CLI entry point for cron and one-shot provisioning — same Core Data stack, and invocable over MCP via the `run_job` tool.               |

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

Custom responders are discovered automatically from `src/Responders/` and `src/ViewControllers/` and take priority over built-in responders in the chain. Custom MCP tools are discovered the same way from `src/MCPTools/`, and background jobs from `src/Jobs/` — the framework scans the source tree rather than reading a registry.

---

## Quick orientation

A minimal application needs one entry point that boots the singleton and calls `run()`:

```php
Application::shared()->run();
```

---

## Requirements

- **PHP 8.5 or newer.** Property hooks and the pipe operator are used
  throughout, so this is a floor rather than a recommendation.
- Extensions `ctype`, `intl`, `mbstring` and `openssl`, plus those the sibling
  libraries require.
- [`sabatier/foundation`](https://github.com/dantesabatier/Foundation) and
  [`sabatier/coredata`](https://github.com/dantesabatier/CoreData).

Rate limiting, idempotency, the authorization cache and MCP session storage
each run over a pluggable store. Installing `ext-apcu`, `ext-redis` or
`ext-memcached` selects a shared backend; with none of them, an in-memory store
applies per process.

Configuration is by environment variable — see [.env.example](.env.example) for
every variable the framework reads, with its default.

---

## Documentation

| Document                             | Covers                                                    |
|--------------------------------------|-----------------------------------------------------------|
| [ARCHITECTURE.md](ARCHITECTURE.md)   | The design, subsystem by subsystem                        |
| [API.md](API.md)                     | How a client talks to a Service application over HTTP     |
| [MCP.md](MCP.md)                     | The MCP tool catalogue and the contract a custom tool honours |
| [CONTRIBUTING.md](CONTRIBUTING.md)   | Setting up, the checks to pass, the conventions to keep   |
| [SECURITY.md](SECURITY.md)           | Reporting a vulnerability, and what is in scope           |
| [CHANGELOG.md](CHANGELOG.md)         | What changed between versions                             |

---

## License

MIT. See `LICENSE.md`.
