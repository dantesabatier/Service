<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\URLCredential;

abstract class Authentication
{
    /** @var ArrayClass<class-string<Authentication>>|null */
    private static ?ArrayClass $registeredAuthenticationClasses = null;
    private(set) ?Authorizable $user {
        get => $this->user ??= ($username = $this->credential?->user) ? new IdentityManager($username, $this->manager->managedObjectContext, $this->manager->isFirstResponder ? $this->manager->request->serialization : null)->currenUser : null;
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
        return $authenticationClasses->first(fn(mixed $authenticationClass) => $authenticationClass::canInit($scheme));
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
     * @param class-string<Authentication> $authenticationClass
     */
    public function unregisterClass(string $authenticationClass): void
    {
        if ($registeredAuthenticationClasses = self::$registeredAuthenticationClasses) {
            $registeredAuthenticationClasses->remove($authenticationClass);
        }
    }

    public abstract static function canInit(AuthenticationScheme $scheme): bool;
}

