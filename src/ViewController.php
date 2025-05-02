<?php

namespace Sabatier\Service;

use ReflectionClass;
use ReflectionProperty;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use function Sabatier\Foundation\class_name;
use const Sabatier\Foundation\kCFBundleNameKey;

/**
 * An abstract class that acts as a base for view controllers, managing the creation, rendering of views and encapsulating the associated contextual data.
 * All view controllers must be {@see Endpoint} annotated.
 */
abstract class ViewController extends Responder
{
    /** @var class-string<Renderer> The class used to render the view controller's view */
    public static string $rendererClass = Renderer::class;
    /** @var string The name of the view controller's template file. The default value is the name of the class */
    public string $name {
        get => $this->name ??= class_name($this->class);
    }
    /** @var View The view that the controller manages. */
    private(set) View $view;
    /** @var array<string, mixed> An associative array consisting of the property names and the properties marked as {@see Outlet} passed to the view's rendering system. */
    public array $context {
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
        get => $this->bundle->object(kCFBundleNameKey);
    }
    public Response $response {
        get {
            if ($this->request->httpMethod === HTTPRequestMethod::get) {
                $this->loadView();
            }
            return parent::$response::get();
        }
    }

    /**
     * Creates the view that the controller manages.
     *
     * You should never call this method directly.
     */
    final public function loadView(): void
    {
        $this->viewWillLoad();
        $this->view = new View($this->name, $this->context, new self::$rendererClass($this->bundle));
        $this->content = $this->view->render();
        $this->headerFields["Content-Type"] = "text/html; charset=utf-8";
        $this->headerFields["Cache-Control"] = "no-cache, no-store, must-revalidate, max-age=0";
        $this->viewDidLoad();
    }

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
