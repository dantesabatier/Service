<?php

namespace Sabatier\Service;

use Sabatier\Foundation\Set;

/**
 * A factory class for managing authentication strategy classes.
 */
final class AuthenticationFactory
{
    /** @var Set<class-string<Authentication>>|null */
    private static ?Set $registeredAuthenticationClasses = null;

    /**
     * @return Set<class-string<Authentication>>
     */
    private static function registeredAuthenticationClasses(): Set
    {
        self::$registeredAuthenticationClasses ??= new Set();
        return self::$registeredAuthenticationClasses;
    }

    /**
     * Attempts to register a subclass of AuthenticationStrategy, making it visible to the access manager.
     *
     * The first AuthenticationStrategy subclass to return true when sent a {@see canHandle()} message is used to authenticate the request. There is no guarantee that all registered authentication classes will be consulted.
     * @param class-string<Authentication> $authenticationClass
     * @return bool true if the registration is successful, false otherwise. The only failure condition is if authenticationClass is not a subclass of AuthenticationStrategy.
     */
    public static function registerClass(string $authenticationClass): bool
    {
        if (!is_subclass_of($authenticationClass, Authentication::class)) {
            return false;
        }
        $registeredAuthenticationClasses = self::registeredAuthenticationClasses();
        $registeredAuthenticationClasses->insert($authenticationClass);
        return true;
    }


    /**
     * Retrieves the authentication class that supports the specified authentication scheme.
     *
     * @param Set<class-string<Authentication>> $authenticationClasses List of authentication classes.
     * @param AuthenticationScheme $scheme The authentication scheme to check for support.
     * @return class-string<Authentication>|null The authentication class that supports the scheme or null if none is found.
     */
    public static function getAuthenticationClass(Set $authenticationClasses, AuthenticationScheme $scheme): ?string
    {
        return $authenticationClasses->first(
        /**
         * @param class-string<Authentication> $authenticationClass
         * @return bool
         */
            fn(string $authenticationClass): bool => $authenticationClass::isSupported($scheme)
        );
    }

    /**
     * @return Set<class-string<Authentication>>|null
     */
    public static function getAuthentications(): ?Set
    {
        return self::$registeredAuthenticationClasses;
    }

    /**
     * Unregisters the specified subclass of AuthenticationStrategy.
     * @param class-string<Authentication> $authenticationClass
     */
    public function unregisterClass(string $authenticationClass): void
    {
        if ($registeredAuthenticationClasses = self::$registeredAuthenticationClasses) {
            $registeredAuthenticationClasses->remove($authenticationClass);
        }
    }
}
