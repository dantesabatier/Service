<?php

namespace Sabatier\Service;

interface AuthenticationProtocol
{
    /**
     * Handles the user authentication process.
     */
    #[Action]
    public function login(): void;

    /**
     * Logs the user out of the current session.
     */
    #[Action]
    public function logout(): void;
}
