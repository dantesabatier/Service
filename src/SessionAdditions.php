<?php

declare(strict_types=1);

namespace Sabatier\Service;

use function Sabatier\Foundation\unsafe_value;

/**
 * Helper functions for safe and consistent session management.
 */

/**
 * Start a session with optional CookieParameters or overrides.
 *
 * @param CookieParameters|null $params Optional cookie parameters
 * @param string|null $savePath Optional custom session storage path
 * @return bool
 */
function session_start_with_params(?CookieParameters $params = null, ?string $savePath = null): bool
{
    return unsafe_value(function () use ($params, $savePath): bool {
        if (session_status() === PHP_SESSION_NONE) {
            if ($params) {
                session_set_cookie_params($params->allValues);
            }
            if ($savePath) {
                session_save_path($savePath);
            }
            return session_start();
        }
        return true;
    });
}

/**
 * Check if a session has been started.
 *
 * @return bool
 */
function session_has_started(): bool
{
    return session_status() === PHP_SESSION_ACTIVE;
}

/**
 * Get a value from the session safely.
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function session_get(string $key, mixed $default = null): mixed
{
    return $_SESSION[$key] ?? $default;
}

/**
 * Set a value in the session safely.
 *
 * @param string $key
 * @param mixed $value
 * @return void
 */
function session_set(string $key, mixed $value): void
{
    $_SESSION[$key] = $value;
}

/**
 * Unset a key from the session safely.
 *
 * @param string $key
 * @return void
 */
function session_unset_key(string $key): void
{
    unset($_SESSION[$key]);
}

/**
 * Regenerate the session ID safely.
 *
 * @param bool $deleteOldSession
 * @return void
 */
function session_regenerate_id_safe(bool $deleteOldSession = true): void
{
    unsafe_value(fn() => session_regenerate_id($deleteOldSession));
}

/**
 * Destroy the current session safely.
 *
 * @return void
 */
function session_destroy_safe(): void
{
    unsafe_value(function () {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    });
}

/**
 * Get current session cookie parameters.
 *
 * @return array
 */
function session_cookie_params_current(): array
{
    return session_get_cookie_params();
}
