# Sabatier Service Framework

**Service** is a multi-purpose application framework for the Singularity ecosystem. Inspired by the architectural elegance of Apple's **AppKit** and **Core Data**, it provides the infrastructure to build both high-performance headless services and sophisticated, stateful web applications with rich user interfaces.

It is the core engine behind **Singularity**, an ambitious IDE running on Electron, demonstrating its capability to power complex, desktop-grade software within the web stack.

## Dual-Core Architecture

**Service** is designed to excel in two distinct paradigms:

1.  **Headless API Service**: Perfect for modern PWAs (React, Vue, etc.). It features a robust **CORS engine**, JSON serialization, and stateless **JWT authentication**.
2.  **Full-Stack Application**: A true "AppKit for the Web". Using `ViewController` and `View` objects, it supports server-side rendering with template engines like **Nette Latte**, Twig, or native PHP, providing a structured lifecycle from `viewWillLoad` to `viewDidLoad`.

## Key Features

- **Responder-Chain Architecture**: Centralized event and request handling through a sophisticated chain of `Responder` objects, ensuring clean separation of concerns.
- **ViewController & Outlets**: Manage UI logic and data binding using `#[Outlet]` attributes, bringing the familiar metaphors of native development to PHP.
- **Core Data Integration**: A first-class persistence layer with `ManagedObjectContext` and `PersistentContainer` for industrial-grade data modeling.
- **Hybrid Security Infrastructure**: Smart, environment-aware security that utilizes JWT for stateless clients, with a seamless **Session-based fallback** for traditional stateful web applications.
- **Modern PHP 8.4 Foundation**: Fully leverages the latest language features, including **Property Hooks** and Attributes, for a declarative and expressive codebase.

## The Singularity Ecosystem

**Service** provides the foundational metaphors—Bundles, Responders, and ViewControllers—that allow developers to build web software with the same depth as native desktop applications. Whether you are building a microservice or a complex IDE like Singularity, the framework scales with your architectural needs.

## Quick Start

### Defining an Endpoint
```php
#[Endpoint("/")]
final class HomeController extends ViewController {
    public string $name = "Home";
    
    #[Outlet]
    public ?string $title {
        get => Bundle::main()->object(kCFBundleNameKey);
    }
}
```
