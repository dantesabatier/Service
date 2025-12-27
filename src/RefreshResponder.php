<?php

namespace Sabatier\Service;

use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Networking\HTTPRequestMethod;

/**
 * Issues a new access token from a valid refresh-capable authentication.
 *
 * This endpoint is publicly accessible but requires an authentication
 * identity with the `refresh` capability.
 *
 * - Requires: Authorization header
 * - Scope required: refresh
 * - Returns: authenticated user and new access token
 *
 * @throws UnauthorizedException if the authentication is invalid or lacks refresh capability
 */
final class RefreshResponder extends Responder
{
    /** @var ArrayClass<string> */
    public ArrayClass $allowedMethods {
        get => new ArrayClass([HTTPRequestMethod::post]);
    }
    public bool $isProtectedContentAvailable = true;

    /**
     * Issues a new JSON Web Token for an already authenticated JWT identity.
     *
     * @throws Exception
     */
    #[Action(decorators: [JSONDecorator::class])]
    public function refresh(): void
    {
        $this->data = Application::shared()->authenticationManager->refresh();
    }
}
