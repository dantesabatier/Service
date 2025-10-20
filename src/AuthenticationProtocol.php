<?php

namespace Sabatier\Service;

abstract class AuthenticationProtocol extends Responder
{
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
