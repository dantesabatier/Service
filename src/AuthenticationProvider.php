<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Set;

/**
 * Resolves and lazily instantiates the concrete {@see Authentication} for the request's scheme.
 *
 * Classes are taken from {@see AuthenticationResolver}; the first registered implementation whose {@see Authentication::isSupported()} returns true is used. The resolved class name and the instance are each computed on first read of the corresponding property.
 *
 * @throws UnimplementedException When no registered class supports the scheme (first read of {@see $authenticationClass} or {@see $authentication}).
 */
final class AuthenticationProvider
{
    /** @var class-string<Authentication> The resolved class name; computed on first read via {@see AuthenticationResolver::getAuthenticationClass()}. */
    private string $authenticationClass {
        get => $this->authenticationClass ??= AuthenticationResolver::getAuthenticationClass(AuthenticationResolver::getAuthentications() ?? new Set(), $this->scheme) ?? throw new UnimplementedException();
    }
    /** @var Authentication The strategy instance; instantiated on first read with {@see $authenticationClass}, {@see $context}, and {@see $environment}. */
    private(set) Authentication $authentication {
        get => $this->authentication ??= new $this->authenticationClass($this->context, $this->environment);
    }

    /**
     * @param AuthenticationScheme $scheme The request's authentication scheme (for example from the Authorization header).
     * @param AuthenticationContext $context The current execution context passed to the concrete {@see Authentication} constructor.
     * @param Dictionary<mixed> $environment Configuration and environmental variables passed to the concrete {@see Authentication} constructor.
     */
    public function __construct(private readonly AuthenticationScheme $scheme, private readonly AuthenticationContext $context, public readonly Dictionary $environment)
    {
    }
}
