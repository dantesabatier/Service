# Sabatier Service Framework

**Service** is a multipurpose application framework for the Singularity ecosystem. Inspired by the architectural elegance of Apple's **AppKit** and **Core Data**, it provides the infrastructure to build both high-performance headless services and sophisticated, stateful web applications with rich user interfaces.

It is the core engine behind **Singularity**, an ambitious IDE running on Electron, demonstrating its ability to power complex, desktop-grade software within the web stack.

## Dual-Core Architecture

**Service** is designed to excel in two distinct paradigms:

1.  **Headless API Service**: Perfect for modern PWAs (React, Vue, etc.). It features a robust **CORS engine**, JSON serialization, and stateless **JWT authentication**.
2.  **Full-Stack Application**: A true "AppKit for the Web." Using `ViewController` and `View` objects, it supports server-side rendering with template engines like **Nette Latte**, Twig, or native PHP, providing a structured lifecycle from `viewWillLoad` to `viewDidLoad`.

## Key Features

- **Responder-Chain Architecture**: Centralized event and request handling through a sophisticated chain of `Responder` objects, ensuring clean separation of concerns.
- **ViewController & Outlets**: Manage UI logic and data binding using `#[Outlet]` attributes, bringing the familiar metaphors of native development to PHP.
- **Core Data Integration**: A first-class persistence layer with `ManagedObjectContext` and `PersistentContainer` for industrial-grade data modeling.
- **Hybrid Security Infrastructure**: Smart, environment-aware security that uses JWT for stateless clients, with a seamless **Session-based fallback** for traditional stateful web applications.
- **Modern PHP 8.4 Foundation**: Fully leverages the latest language features, including **Property Hooks** and Attributes, for a declarative and expressive codebase.

## The Singularity Ecosystem

**Service** provides the foundational metaphors—Bundles, Responders, and ViewControllers—that allow developers to build web software with the same depth as native desktop applications. Whether you are building a microservice or a complex IDE like Singularity, the framework scales with your architectural needs.

## Quick Start

### Defining Endpoints and Actions
The framework uses a declarative approach to map requests to logic. While an `#[Endpoint]` defines the base route of a class, an `#[Action]` maps specific HTTP methods to internal methods, allowing for complex behaviors with built-in response decoration.
```php
#[Endpoint("/")]
final class HomeController extends ViewController {
    public string $name = "Home";
    
    #[Outlet]
    public ?string $title {
        get => Bundle::main()->object(kCFBundleNameKey);
    }
    
    #[Action(method: HTTPRequestMethod::post, decorators: [JSONDecorator::class])]
    public function login(): void {
        $user = $this->authentication->authenticatedUser ?? throw new UnauthorizedException();
        
        // Logical processing...
        $this->data = new Dictionary(["user" => $user]);
    }
}
```
## Infrastructure Configuration

The framework automatically adjusts its behavior based on your environment, ensuring the best balance between security and performance:

- **JWT Mode**: Activated when `JWTPrivateKey` is present in your environment. The system operates in a stateless manner, ignoring session infrastructure to maximize scalability for PWAs and mobile clients.
- **Session Fallback**: Automatically engaged for stateful interactions or when JWT is not configured. This ensures a "secure by default" experience for traditional web applications and browsers.

## Requirements

- **PHP 8.4+** (leveraging Property Hooks).
- **Sabatier Foundation & CoreData** libraries.
- **OpenSSL** (for JWT operations).

## License

This project is licensed under the MIT License. See the `LICENSE.md` file for details.
