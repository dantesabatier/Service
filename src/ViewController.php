<?php

namespace Sabatier\Service;

use ReflectionClass;
use ReflectionProperty;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use const Sabatier\Foundation\kCFBundleNameKey;

/**
 * @property-read bool $isViewLoaded A Boolean value indicating whether the view is currently loaded into memory.
 */
abstract class ViewController extends Responder
{
    /** @var class-string<Renderer> */
    public static string $rendererClass = NativeRenderer::class;
    private static ?Renderer $renderer = null;
    /** @var string The name of the view controller's template file, if one was specified. */
    public string $name;
    /** @var View The view that the controller manages. */
    public readonly View $view;
    /** @var object|array<string, mixed> */
    public object|array $context = [];
    /** @var Bundle The view controller's template bundle if it exists. */
    public Bundle $bundle;
    /** @var string|null A localized string that represents the view this controller manages. */
    #[Outlet]
    public ?string $title = null;

    public function __construct()
    {
        parent::__construct();
        unset($this->name);
        unset($this->view);
        unset($this->context);
        unset($this->bundle);
        unset($this->title);
    }

    public function __get(string $name)
    {
        if ($name == "name") {
            $this->$name = self::className();
            return $this->$name;
        } elseif ($name == "context") {
            $this->$name = array_reduce((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC), function (array $context, ReflectionProperty $property): array {
                if ($property->getAttributes(Outlet::class) !== []) {
                    $context[$property->name] = $this->valueForKey($property->name);
                }
                return $context;
            }, []);
            return $this->$name;
        } elseif ($name == "view") {
            $this->viewWillLoad();
            if (self::$renderer === null) {
                self::$renderer = new self::$rendererClass($this->bundle);
            }
            $this->$name = new View($this->name, $this->context, self::$renderer);
            $this->viewDidLoad();
            return $this->$name;
        } elseif ($name == "bundle") {
            $this->$name = Bundle::main();
            return $this->$name;
        } elseif ($name == "title") {
            $this->$name = $this->bundle->object(kCFBundleNameKey);
            return $this->$name;
        } elseif ($name == "isViewLoaded") {
            return isset($this->view);
        } else {
            return parent::__get($name);
        }
    }

    public function loadView(): void
    {
        $this->content = $this->view->render();
        $this->contentType = "text/html; charset=utf-8";
    }

    public function viewWillLoad(): void
    {
    }

    public function viewDidLoad(): void
    {
    }

    public function response(): HTTPURLResponse
    {
        if ($this->request->httpMethod === HTTPRequestMethod::get) {
            $this->loadView();
        }
        return new HTTPURLResponse($this->request->url);
    }
}
