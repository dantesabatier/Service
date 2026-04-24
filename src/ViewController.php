<?php

namespace Sabatier\Service;

use Override;
use ReflectionClass;
use ReflectionProperty;
use Sabatier\Foundation\Bundle;
use function Sabatier\Foundation\class_name;
use const Sabatier\Foundation\kCFBundleNameKey;

/**
 * A base class for responders that render HTML views using a template engine.
 *
 * `ViewController` extends `Responder` with a view lifecycle: it discovers `#[Outlet]`-annotated
 * public properties, passes them as the rendering context to the configured `Renderer`, and sets the
 * resulting HTML string as the response body. The template name defaults to the short class name.
 *
 * ## View lifecycle
 * 1. `viewWillLoad()` — override to set `$this->title` or any outlet before rendering.
 * 2. View is instantiated with the outlet context and rendered by `$rendererClass`.
 * 3. `viewDidLoad()` — override for post-render setup (rarely needed).
 *
 * ## Outlets
 * Declare public properties annotated with `#[Outlet]` to expose data to the template:
 *
 * <code>
 * #[Outlet]
 * public string $username = "guest";
 * </code>
 *
 * ## Renderer
 * Override the static `$rendererClass` property (e.g. in the application delegate) to swap
 * the template engine globally:
 *
 * <code>
 * ViewController::$rendererClass = LatteRenderer::class;
 * </code>
 *
 * All subclasses must be annotated with `#[Endpoint]`.
 *
 * @see Endpoint
 * @see Outlet
 * @see Renderer
 */
abstract class ViewController extends Responder
{
    /** @var class-string<Renderer> The class used to render the view controller's view */
    public static string $rendererClass = Renderer::class;
    /** @var string The name of the view controller's template file. The default value is the name of the class */
    protected string $name {
        get => $this->name ??= class_name($this->class);
    }
    /** @var View The view that the controller manages. */
    private(set) View $view {
        get {
            if ($this->isViewLoaded) {
                return $this->view;
            }
            $this->isViewLoaded = true;
            $this->viewWillLoad();
            $this->view = new View($this->name, $this->context, new self::$rendererClass($this->bundle));
            $this->viewDidLoad();
            return $this->view;
        }
    }
    /** @var bool A Boolean value indicating whether the view is currently loaded into memory. */
    private(set) bool $isViewLoaded = false;
    /** @var array<string, mixed> An associative array consisting of the property names and the properties marked as {@see Outlet} passed to the view's rendering system. */
    protected array $context {
        get => $this->context ??= array_reduce(new ReflectionClass($this)->getProperties(ReflectionProperty::IS_PUBLIC), function (array $context, ReflectionProperty $property): array {
            if ($property->getAttributes(Outlet::class) !== []) {
                $context[$property->name] = $this->valueForKey($property->name);
            }
            return $context;
        }, []);
    }
    /** @var Bundle The view controller's template bundle if it exists. */
    public Bundle $bundle {
        get => $this->bundle ??= Bundle::main();
    }
    /** @var string|null A localized string that represents the view this controller manages. */
    #[Outlet]
    public ?string $title {
        get => $this->title ??= $this->bundle->object(kCFBundleNameKey);
    }
    #[Override]
    protected mixed $data {
        get => $this->data ??= $this->view->render();
    }

    /**
     * Called before the controller's view is loaded into memory.
     */
    public function viewWillLoad(): void
    {
    }

    /**
     * Called after the controller's view is loaded into memory.
     */
    public function viewDidLoad(): void
    {
    }
}
