<?php

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\URLCredential;
use function Sabatier\Foundation\request_concrete_implementation;

abstract class Authentication
{
    /** @var ArrayClass<class-string<Authentication>>|null */
    private static ?ArrayClass $registeredAuthenticationClasses = null;
    public ?Authorizable $user {
        get => $this->user ??= $this->user();
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

    private static function registeredAuthenticationClasses(): ArrayClass
    {
        self::$registeredAuthenticationClasses ??= new ArrayClass();
        return self::$registeredAuthenticationClasses;
    }

    public static function registerClass(string $authenticationClass): bool
    {
        $registeredAuthenticationClasses = self::registeredAuthenticationClasses();
        if (!$registeredAuthenticationClasses->containsElement($authenticationClass)) {
            $registeredAuthenticationClasses[] = $authenticationClass;
        }
        return true;
    }

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

    private function user(): ?Authorizable
    {
        if (!($username = $this->credential?->user)) {
            return null;
        }
        return new IdentityManager($username, $this->manager->managedObjectContext, $this->manager->isFirstResponder ? $this->manager->request->serialization : null)->currenUser;
    }
}

