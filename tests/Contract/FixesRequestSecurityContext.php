<?php

declare(strict_types=1);

namespace Sabatier\Service\Testing;

use PHPUnit\Framework\Attributes\After;
use ReflectionClass;
use ReflectionProperty;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Service\Authorizable;
use Sabatier\Service\RequestSecurityContext;

/**
 * Fixes a {@see RequestSecurityContext} for the rest of a test, and retires it when the test ends.
 */
trait FixesRequestSecurityContext
{
    private bool $isRequestSecurityContextFixed = false;
    private ?RequestSecurityContext $previousRequestSecurityContext = null;

    protected function fixRequestSecurityContext(RequestSecurityContext $context): void
    {
        $current = new ReflectionProperty(RequestSecurityContext::class, "current");
        if (!$this->isRequestSecurityContextFixed) {
            /** @var RequestSecurityContext|null $previous */
            $previous = $current->getValue();
            $this->previousRequestSecurityContext = $previous;
            $this->isRequestSecurityContextFixed = true;
        }
        $current->setValue(null, $context);
    }

    protected function unrestrictedRequestSecurityContext(): RequestSecurityContext
    {
        return new RequestSecurityContext(null, new ArrayClass(), false, $this->unsavedManagedObjectContext());
    }

    /**
     * @param ArrayClass<string>|null $scopes
     */
    protected function restrictedRequestSecurityContext(?Authorizable $user, ?ArrayClass $scopes = null, ?ManagedObjectContext $managedObjectContext = null): RequestSecurityContext
    {
        return new RequestSecurityContext($user, $scopes ?? new ArrayClass(), true, $managedObjectContext ?? $this->unsavedManagedObjectContext());
    }

    #[After]
    protected function retireRequestSecurityContext(): void
    {
        if (!$this->isRequestSecurityContextFixed) {
            return;
        }
        new ReflectionProperty(RequestSecurityContext::class, "current")->setValue(null, $this->previousRequestSecurityContext);
        $this->isRequestSecurityContextFixed = false;
        $this->previousRequestSecurityContext = null;
    }

    private function unsavedManagedObjectContext(): ManagedObjectContext
    {
        return new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
    }
}
