<?php

namespace Sabatier\Service;

/**
 * Abstract class representing an authentication protocol.
 *
 * This class defines the structure for managing user authentication,
 * including login and logout functionalities.
 */
abstract class AuthenticationManager extends Responder
{
    /**
     * Retrieves authentication details.
     */
    abstract public Authentication $authentication {
        get;
    }

    /**
     * Handles the user authentication process.
     */
    #[Action]
    abstract public function login(): void;

    /**
     * Logs the user out of the current session.
     */
    #[Action]
    abstract public function logout(): void;
}
