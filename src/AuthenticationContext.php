<?php

declare(strict_types=1);

namespace Sabatier\Service;

use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/**
 * Represents the context for authentication processes.
 */
final readonly class AuthenticationContext
{
    /**
     * Initializes a new instance of the AuthenticationContext class.
     *
     * @param AuthorizationHeader $authorizationHeader The authorization header.
     * @param string $httpMethod The HTTP request method.
     * @param ManagedObjectContext $managedObjectContext The managed object context.
     * @param Dictionary<mixed>|null $serialization The serialization dictionary, if applicable.
     * @param AuthenticationService $authenticationService The authentication service.
     * @param AuthorizationService|null $authorizationService The authorization service an identity without a token reads its authorization scopes from.
     */
    public function __construct(public AuthorizationHeader $authorizationHeader, #[ExpectedValues(valuesFromClass: HTTPRequestMethod::class)] public string $httpMethod, public ManagedObjectContext $managedObjectContext, public ?Dictionary $serialization, public AuthenticationService $authenticationService, public ?AuthorizationService $authorizationService = null)
    {
    }
}
