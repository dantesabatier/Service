<?php

namespace Sabatier\Service;

use ReflectionClass;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\CompareOptions;
use Sabatier\Foundation\DirectoryEnumerationOptions;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\FlattenSequence;
use Sabatier\Foundation\URL;
use function Sabatier\Foundation\string_is_equal;

/** @internal */
final class FirstResponderResolver
{
    public Responder $firstResponder {
        get => $this->resolveFirstResponder();
    }

    public function __construct(private readonly Application $application, private readonly Responder $defaultResponder)
    {
    }

    /**
     * @param string $namespaceName
     * @param URL $directoryURL
     * @param URL $fileURL
     * @return class-string<Responder>
     */
    private function buildClassName(string $namespaceName, URL $directoryURL, URL $fileURL): string
    {
        $directoryComponent = $directoryURL->lastPathComponent;
        $fileNameWithoutExtension = FileManager::default()->displayName($fileURL->path);
        /** @var class-string<Responder> */
        return "$namespaceName\\$directoryComponent\\$fileNameWithoutExtension";
    }

    private function isValidResponderClass(string $className): bool
    {
        if (!class_exists($className) || !is_subclass_of($className, Responder::class)) {
            return false;
        }
        $reflectionClass = new ReflectionClass($className);
        return $reflectionClass->isInstantiable();
    }

    /**
     * @param URL $directoryURL
     * @return ArrayClass<URL>
     */
    private function filteredFileURLs(URL $directoryURL): ArrayClass
    {
        return FileManager::default()->contentsOfDirectory($directoryURL, null, DirectoryEnumerationOptions::skipsHiddenFiles)->filter(fn(URL $url): bool => string_is_equal($url->pathExtension, "php", CompareOptions::caseInsensitive));
    }

    /**
     * @param string $namespaceName
     * @param URL $directoryURL
     * @return ArrayClass<class-string<Responder>>|null
     */
    private function responderClasses(string $namespaceName, URL $directoryURL): ?ArrayClass
    {
        if (!FileManager::default()->fileExists($directoryURL->path)) {
            return null;
        }
        return $this->filteredFileURLs($directoryURL)->map(fn(URL $fileURL): string => $this->buildClassName($namespaceName, $directoryURL, $fileURL))->filter($this->isValidResponderClass(...));
    }

    /**
     * @param string $namespaceName
     * @return FlattenSequence<class-string<Responder>>
     */
    private function discoverResponderClasses(string $namespaceName): FlattenSequence
    {
        $directoryURL = Bundle::main()->bundleURL->appendingPathComponent("src");
        /** @var FlattenSequence<class-string<Responder>> */
        return new ArrayClass([RespondersDirectory, ViewControllersDirectory])->compactMap(fn(string $directoryName): ?ArrayClass => $this->responderClasses($namespaceName, $directoryURL->appendingPathComponent($directoryName)))->joined();
    }

    /**
     * @param ArrayClass<Responder> $responders
     * @return Responder
     */
    private function buildResponderChain(ArrayClass $responders): Responder
    {
        $previous = null;
        foreach ($responders as $responder) {
            if ($previous) {
                $previous->nextResponder = $responder;
            }
            $previous = $responder;
        }
        return $responders[0];
    }

    private function mergeResponderChains(?Responder $responder1, ?Responder $responder2): ?Responder
    {
        if (!$responder1) {
            return $responder2;
        }
        $last = $responder1;
        while ($last->nextResponder) {
            $last = $last->nextResponder;
        }
        $last->nextResponder = $responder2;
        return $responder1;
    }

    private function customResponder(): ?Responder
    {
        if (!($delegate = $this->application->delegate)) {
            return null;
        }
        $namespaceName = new ReflectionClass($delegate)->getNamespaceName();
        $responderClasses = $this->discoverResponderClasses($namespaceName);
        /** @var ArrayClass<Responder> $responders */
        $responders = $responderClasses->map(
        /**
         * @param class-string<Responder> $responderClass
         */
            fn(string $responderClass): Responder => new $responderClass());
        if ($responders->isEmpty) {
            return null;
        }
        return $this->buildResponderChain($responders);
    }

    private function initialResponder(): ?Responder
    {
        return $this->mergeResponderChains($this->customResponder(), $this->buildResponderChain(new ArrayClass([$this->defaultResponder, new PersistentSpace(), new ResourceManager(), new RefreshResponder(), new Preferences(), new Uploader(), new Downloader(), new HomeController()])));
    }

    private function findFirstResponder(): Responder
    {
        $responder = $this->initialResponder();
        while ($responder) {
            if ($responder->isFirstResponder) {
                return $responder;
            }
            $responder = $responder->nextResponder;
        }
        throw new NotFoundException("The requested URL was not found on this server {$this->application->request->url}");
    }

    private function resolveFirstResponder(): Responder
    {
        if ($this->application->request->isPreflight) {
            return $this->application;
        }
        return $this->findFirstResponder();
    }
}
