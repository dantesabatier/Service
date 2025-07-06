<?php

namespace Sabatier\Service;

use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\URLCredential;

/**
 * Represents a class for managing authentication mechanisms.
 */
abstract class Authentication
{
    /** @var ArrayClass<class-string<Authentication>>|null */
    private static ?ArrayClass $registeredAuthenticationClasses = null;
    public abstract AuthenticationScheme $scheme {
        get;
    }
    public abstract ?URLCredential $credential {
        get;
    }
    public ?Authorizable $user {
        get {
            if (!($username = $this->credential?->user)) {
                return null;
            }
            return new IdentityManager($username, $this->context, $this->serialization)->user;
        }
    }
    public abstract bool $isValid {
        get;
    }

    public function __construct(public readonly Request $request, public readonly ManagedObjectContext $context, public readonly ?Dictionary $serialization = null)
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
     * The first Authentication subclass to return true when sent a {@see canHandle()} message is used to authenticate the request. There is no guarantee that all registered authentication classes will be consulted.
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
     * @param Request $request
     * @return class-string<Authentication>|null
     * @internal
     */

    public static function getAuthenticationClass(ArrayClass $authenticationClasses, Request $request): ?string
    {
        return $authenticationClasses->first(
        /**
         * @param class-string<Authentication> $authenticationClass
         * @return bool
         */
            fn(string $authenticationClass): bool => $authenticationClass::canHandle($request)
        );
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
     * Determines whether the authentication subclass can handle the specified request.
     * @param Request $request The request.
     * @return bool true if the authentication subclass can handle the request, otherwise false.
     */
    public abstract static function canHandle(Request $request): bool;
}

