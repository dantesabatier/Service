<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\URLCredential;
use function Sabatier\Foundation\request_concrete_implementation;

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
     * @param class-string<Authentication> $authenticationClass
     * @return bool
     */
    public static function registerClass(string $authenticationClass): bool
    {
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

    public static function canInit(AuthenticationScheme $scheme): bool
    {
        request_concrete_implementation(static::class, __FUNCTION__);
    }
}

