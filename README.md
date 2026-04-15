# Sabatier Service Framework

**Service** is a multipurpose application framework inspired by the architectural elegance of Apple's **AppKit** and **Core Data**. It provides the infrastructure to build both high-performance headless services and sophisticated, stateful web applications with rich user interfaces.

## Dual-Core Architecture

**Service** is designed to excel in two distinct paradigms:

1.  **Headless API Service**: Perfect for modern PWAs (React, Vue, etc.). It features a robust **CORS engine**, JSON serialization, and stateless **JWT authentication**.
2.  **Full-Stack Application**: A true "AppKit for the Web." Using `ViewController` and `View` objects, it supports server-side rendering via pluggable renderers (including native PHP), providing a structured lifecycle from `viewWillLoad` to `viewDidLoad`.

## Key Features

- **Responder-Chain Architecture**: Centralized event and request handling through a sophisticated chain of `Responder` objects, ensuring clean separation of concerns.
- **ViewController & Outlets**: Manage UI logic and data binding using `#[Outlet]` attributes, bringing the familiar metaphors of native development to PHP.
- **Core Data Integration**: A first-class persistence layer with `ManagedObjectContext` and `PersistentContainer` for industrial-grade data modeling.
- **Hybrid Security Infrastructure**: Smart, environment-aware security that uses JWT for stateless clients, with a seamless **Session-based fallback** for traditional stateful web applications.
- **Modern PHP Foundation**: Fully leverages the latest language features, including **Property Hooks** and Attributes, for a declarative and expressive codebase.

## Request Lifecycle (Overview)

Every request follows a consistent, centralized pipeline:

1. Preflight handling (when needed)
2. Access evaluation
3. Endpoint resolution and execution
4. Response decoration (e.g., JSON/HTML/CORS)
5. Final response delivery

This keeps endpoint code minimal and ensures errors are always returned in a structured, predictable format.

## Positioning

**Service** is the application runtime of this stack, designed for both headless APIs and rich server-rendered apps. It provides the responder pipeline, security, persistence integration, and view rendering infrastructure needed to build complex software without boilerplate, without manual routing, and without CLI-driven scaffolding.

## Quick Start

### Defining Endpoints and Actions
The framework uses a declarative approach to map requests to logic. An `#[Endpoint]` defines a class-level route for read access (GET), while `#[Action]` methods represent explicit state changes (mutations) such as PATCH/POST/DELETE. If a class is not an endpoint, it does not serve GET. If it defines no actions, it does not mutate state.
```php
#[Endpoint("/preferences", transformers: [JSONTransformer::class])]
final class Preferences extends Responder {
    #[Override]
    protected mixed $data {
        get => $this->data ??= UserDefaults::standard()->dictionaryRepresentation();
    }

    #[Action(method: HTTPRequestMethod::patch, transformers: [JSONTransformer::class])]
    public function update(): void {
        foreach ($this->request->parsedBody as $key => $value) {
            UserDefaults::standard()->setObject($value, $key);
        }
    }
}
```

### ViewController + Outlets
`ViewController` renders server-side templates and exposes data via `#[Outlet]` properties. Outlets are reflected into the view context automatically, so templates receive the values without manual wiring.
```php
#[Endpoint("/", transformers: [HTMLTransformer::class])]
final class HomeController extends ViewController {
    protected string $name = "Home";

    #[Outlet]
    public ?string $title {
        get => Bundle::main()->object(kCFBundleNameKey);
    }
}
```

## Infrastructure Configuration

The framework automatically adjusts its behavior based on your environment, ensuring the best balance between security and performance:

- **JWT Mode**: Activated when `JWTPrivateKey` is present in your environment. The system operates in a stateless manner, ignoring session infrastructure to maximize scalability for PWAs and mobile clients.
- **Session Fallback**: Automatically engaged for stateful interactions or when JWT is not configured. This ensures a "secure by default" experience for traditional web applications and browsers.

## Requirements

- **PHP** (see `composer.json` for supported versions).
- **Sabatier Foundation & CoreData** libraries.
- **OpenSSL** (for JWT operations).

## License

This project is licensed under the MIT License. See the `LICENSE.md` file for details.

