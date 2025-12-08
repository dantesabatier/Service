<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;

/** @internal */
class AuthenticationStrategyFactory
{
    /** @var ArrayClass<class-string<AuthenticationStrategy>>|null */
    private static ?ArrayClass $registeredAuthenticationStrategyClasses = null;

    /**
     * @return ArrayClass<class-string<AuthenticationStrategy>>
     */
    private static function registeredAuthenticationClasses(): ArrayClass
    {
        self::$registeredAuthenticationStrategyClasses ??= new ArrayClass();
        return self::$registeredAuthenticationStrategyClasses;
    }

    /**
     * Attempts to register a subclass of AuthenticationStrategy, making it visible to the access manager.
     *
     * The first AuthenticationStrategy subclass to return true when sent a {@see canHandle()} message is used to authenticate the request. There is no guarantee that all registered authentication classes will be consulted.
     * @param class-string<AuthenticationStrategy> $authenticationStrategyClass
     * @return bool true if the registration is successful, false otherwise. The only failure condition is if authenticationClass is not a subclass of AuthenticationStrategy.
     */
    public static function registerClass(string $authenticationStrategyClass): bool
    {
        if (!is_subclass_of($authenticationStrategyClass, AuthenticationStrategy::class)) {
            return false;
        }
        $registeredAuthenticationClasses = self::registeredAuthenticationClasses();
        if (!$registeredAuthenticationClasses->containsElement($authenticationStrategyClass)) {
            $registeredAuthenticationClasses[] = $authenticationStrategyClass;
        }
        return true;
    }


    /**
     * Retrieves the authentication class that supports the specified authentication scheme.
     *
     * @param ArrayClass<class-string<AuthenticationStrategy>> $authenticationStrategyClasses List of authentication classes.
     * @param AuthenticationScheme $scheme The authentication scheme to check for support.
     * @return class-string<AuthenticationStrategy>|null The authentication class that supports the scheme or null if none is found.
     */
    public static function getAuthenticationStrategyClass(ArrayClass $authenticationStrategyClasses, AuthenticationScheme $scheme): ?string
    {
        return $authenticationStrategyClasses->first(
        /**
         * @param class-string<AuthenticationStrategy> $authenticationStrategyClass
         * @return bool
         */
            fn(string $authenticationStrategyClass): bool => $authenticationStrategyClass::isSupported($scheme)
        );
    }

    /**
     * @return ArrayClass<class-string<AuthenticationStrategy>>|null
     */
    public static function getAuthenticationStrategies(): ?ArrayClass
    {
        return self::$registeredAuthenticationStrategyClasses;
    }

    /**
     * Unregisters the specified subclass of AuthenticationStrategy.
     * @param class-string<AuthenticationStrategy> $authenticationStrategyClass
     */
    public function unregisterClass(string $authenticationStrategyClass): void
    {
        if ($registeredAuthenticationClasses = self::$registeredAuthenticationStrategyClasses) {
            $registeredAuthenticationClasses->remove($authenticationStrategyClass);
        }
    }
}
