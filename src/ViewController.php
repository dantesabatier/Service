<?php

namespace Sabatier\Service;

use ReflectionClass;
use ReflectionProperty;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Foundation\Networking\HTTPURLResponse;
use function Sabatier\Foundation\class_name;
use const Sabatier\Foundation\kCFBundleNameKey;

abstract class ViewController extends Responder
{
    public string $name;
    public readonly View $view;
    /** @var object|array<string, mixed> */
    public object|array $context = [];
    public Bundle $bundle;
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
        if ($name == 'name') {
            $this->$name = str_ireplace(class_name(self::class), '', class_name(static::class));
            return $this->$name;
        } elseif ($name == 'context') {
            $this->$name = array_reduce((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC), function (array $context, ReflectionProperty $property): array {
                if ($property->getAttributes(Outlet::class) !== []) {
                    $context[$property->name] = $this->valueForKey($property->name);
                }
                return $context;
            }, []);
            return $this->$name;
        } elseif ($name == 'view') {
            $this->viewWillLoad();
            /** @var class-string<View> $viewClass */
            $viewClass = static::viewClass();
            $viewClass::initialize();
            $this->$name = new $viewClass($this->name, $this->context, $this->bundle);
            $this->viewDidLoad();
            return $this->$name;
        } elseif ($name == 'bundle') {
            $this->$name = Bundle::main();
            return $this->$name;
        } elseif ($name == 'title') {
            $this->$name = $this->bundle->object(kCFBundleNameKey);
            return $this->$name;
        } else {
            return parent::__get($name);
        }
    }

    /**
     * @return class-string<View>
     */
    public static function viewClass(): string
    {
        return View::class;
    }

    public function loadView(): void
    {
        $this->content = (string)$this->view;
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
        return parent::response();
    }
}
