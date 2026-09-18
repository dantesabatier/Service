# Sabatier Service

**Service** is the application and HTTP layer of the PHP 8.5+ **Sabatier SDK**. The SDK is formed by three sibling frameworks — [Foundation](https://github.com/dantesabatier/Foundation), [CoreData](https://github.com/dantesabatier/CoreData) and Service — combining system primitives, managed persistence and application infrastructure for server-side PHP development.

It covers the full spectrum from REST APIs that require no programmer-written code to complex server-rendered applications, with a consistent request pipeline, layered security, and a built-in MCP server for LLM tool access. Scheduled and one-shot background work runs through a parallel CLI entry point that shares the same Core Data stack.

---

## What makes it different

**The model is the API.** Define the data model in Singularity, and the generated Service application automatically exposes a fully functional, secured REST endpoint for every Core Data entity. That baseline API needs no controller, serializer, route registration or programmer-written code.

**Security is structural, not optional.** Authentication, authorization, field-level read/write control, rate limiting, idempotency, CORS, and security headers are wired into the framework's pipeline. You opt out of restrictions rather than opting in.

**The responder chain routes requests.** There are no routing tables. Every request walks a chain of `Responder` objects; the first one that recognizes the URL handles it. Custom endpoints extend `Responder` (or `ViewController` for HTML) and declare their route with a single `#[Endpoint]` attribute.

**It self-adapts to the environment.** JWT mode activates automatically when `JWT_PRIVATE_KEY` is present; session-based auth is the fallback. Rate limiting, idempotency, and CORS policies are configured through environment variables, while their storage backends remain explicit application choices.

---

## Core capabilities

| Capability                | Description                                                                                                                                                                                                                                                                                                                                                                                                                                   |
|---------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **PersistentSpace**       | Automatic CRUD REST API over every Core Data entity. Field-level security and ownership scoping included.                                                                                                                                                                                                                                                                                                                                     |
| **Responder Chain**       | Chain-of-responsibility request routing. Built-in responders cover static files, uploads, downloads, preferences, and an MCP endpoint.                                                                                                                                                                                                                                                                                                        |
| **Security Pipeline**     | Basic, Bearer (JWT), and Digest authentication. Role-based authorization. Per-field read/write access control. Rate limiting with APCu, Redis, or Memcached backends.                                                                                                                                                                                                                                                                         |
| **MCP Server**            | Exposes the application to LLM agents as JSON-RPC 2.0 tools — `describe_model`, `fetch`, `count`, `aggregate`, `group_by`, `create`, `update`, `delete`, `persistent_history`, `run_job`, `get_server_time`, and `web_search`. The data catalogue is generated from the Core Data model; the remaining tools cover history, domain jobs, server time and provider-backed web search. |
| **Server-Side Rendering** | `ViewController` manages a template lifecycle (`viewWillLoad` / `viewDidLoad`) with `#[Outlet]` properties reflected into the rendering context. Pluggable renderer engine.                                                                                                                                                                                                                                                                   |
| **Event Streaming**       | First-class Server-Sent Events support. A responder returns an `EventStreamResponse` of `ServerSentEvent` objects, streamed through an `EventStreamEmitter`.                                                                                                                                                                                                                                                                                  |
| **Scheduled Jobs**        | `Job` subclasses in `src/Jobs/`, auto-discovered and keyed by a `name` hook, run through a CLI entry point for cron and one-shot provisioning — same Core Data stack, and invocable over MCP via the `run_job` tool.                                                                                                                                                                                                                          |

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

## Role in the Sabatier SDK

The Sabatier SDK is the complete Foundation + CoreData + Service stack. Foundation supplies the object and system primitives, CoreData supplies modeling and persistence, and Service supplies the HTTP runtime, responder pipeline, security, MCP server and agent infrastructure. Service is therefore one framework within the SDK, not the SDK by itself or a standalone application template.

**Singularity is the supported application-authoring surface for the SDK.** It designs the Core Data model and generates every file and directory the project needs, including `Info.plist`, the `.mom` model, managed-object subclasses, application delegate and entry points. At that point the model already operates through Service as a REST API without the programmer writing application code.

The generated project is a starting point, not a ceiling. A programmer can add responders, view controllers, domain services, MCP tools, jobs, custom policies and frontend integration until the same foundation supports an application of any required complexity.

The generated HTTP entry point remains intentionally thin:

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
each have pluggable stores, but extensions are not selected automatically. The
default rate-limit, idempotency and MCP session stores use APCu, so the standard
configuration requires `ext-apcu`. An application can explicitly replace those
properties with the supplied Redis, Memcached or in-memory implementations where
available; the authorization cache is in-memory per request unless the application
configures its optional persistent cache.

Configuration is by environment variable — see [.env.example](.env.example) for
every variable the framework reads, with its default.

---

## Installation

```bash
composer require sabatier/service
```

Foundation and CoreData come with it; they are declared dependencies rather
than a separate step.

Most applications do not start from an empty directory. [Singularity](https://github.com/dantesabatier/Singularity),
the stack's authoring environment, generates the whole project — entry points,
application delegate, model and `.env` — from a data model you design in it.
Reach for `composer require` when you are adding Service to something that
already exists.

---

## Documentation

| Document                           | Covers                                                        |
|------------------------------------|---------------------------------------------------------------|
| [ARCHITECTURE.md](ARCHITECTURE.md) | The design, subsystem by subsystem                            |
| [API.md](API.md)                   | How a client talks to a Service application over HTTP         |
| [MCP.md](MCP.md)                   | The MCP tool catalogue and the contract a custom tool honours |
| [LLM.md](LLM.md)                   | Configuring providers, agents, budgets, tools and retrieval   |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Setting up, the checks to pass, the conventions to keep       |
| [RELEASING.md](RELEASING.md)       | Pre-release verification and version-cut checklist            |
| [SECURITY.md](SECURITY.md)         | Reporting a vulnerability, and what is in scope               |
| [CHANGELOG.md](CHANGELOG.md)       | What changed between versions                                 |

---

## License

MIT. See `LICENSE.md`.
