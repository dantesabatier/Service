<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\URLCredential;

abstract class Authentication
{
    /** @var ArrayClass<class-string<Authentication>>|null */
    private static ?ArrayClass $registeredAuthenticationClasses = null;
    public ?Authorizable $user {
        get {
            if (!($username = $this->credential?->user)) {
                return null;
            }
            return new IdentityManager($username, $this->manager->managedObjectContext, $this->manager->isFirstResponder ? $this->manager->request->serialization : null)->currenUser;
        }
    }
    public abstract ?URLCredential $credential {
        get;
    }
    public abstract bool $isValid {
        get;
    }

    public function __construct(public readonly AccessManager $manager)
    {
    }

    /**
     * @return ArrayClass<class-string<Authentication>>
     */
    private static function registeredAuthenticationClasses(): ArrayClass
    {
        self::$registeredAuthenticationClasses ??= new ArrayClass();
        return self::$registeredAuthenticationClasses;
    }

    /**
     * Attempts to register a subclass of Authentication, making it visible to the access manager.
     *
     * The first Authentication subclass to return true when sent a {@see canInit()} message is used to authenticate the request. There is no guarantee that all registered authentication classes will be consulted.
     * @param class-string<Authentication> $authenticationClass
     * @return bool true if the registration is successful, false otherwise. The only failure condition is if authenticationClass is not a subclass of Authentication.
     */
    public static function registerClass(string $authenticationClass): bool
    {
        if (!is_subclass_of($authenticationClass, Authentication::class)) {
            return false;
        }
        $registeredAuthenticationClasses = self::registeredAuthenticationClasses();
        if (!$registeredAuthenticationClasses->containsElement($authenticationClass)) {
            $registeredAuthenticationClasses[] = $authenticationClass;
        }
        return true;
    }

    /**
     * @param ArrayClass<class-string<Authentication>> $authenticationClasses
     * @param AuthenticationScheme $scheme
     * @return class-string<Authentication>|null
     * @internal
     */

    public static function getAuthenticationClass(ArrayClass $authenticationClasses, AuthenticationScheme $scheme): ?string
    {
        return $authenticationClasses->first(fn(mixed $authenticationClass): bool => $authenticationClass::canInit($scheme));
    }

    /**
     * @return ArrayClass<class-string<Authentication>>|null
     * @internal
     */
    public static function getAuthentications(): ?ArrayClass
    {
        return self::$registeredAuthenticationClasses;
    }

    /**
     * Unregisters the specified subclass of Authentication.
     * @param class-string<Authentication> $authenticationClass
     */
    public function unregisterClass(string $authenticationClass): void
    {
        if ($registeredAuthenticationClasses = self::$registeredAuthenticationClasses) {
            $registeredAuthenticationClasses->remove($authenticationClass);
        }
    }

    /**
     * Determines whether the authentication subclass can handle the specified authentication scheme.
     * @param AuthenticationScheme $scheme The authentication scheme.
     * @return bool true if the authentication subclass can handle authentication scheme, otherwise false.
     */
    public abstract static function canInit(AuthenticationScheme $scheme): bool;
}

