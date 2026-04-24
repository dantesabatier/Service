<?php

namespace Sabatier\Service;

use Exception;
use Override;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\DirectoryEnumerationOptions;
use Sabatier\Foundation\FileAttributeKey;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLResourceKey;
use function Sabatier\Foundation\request_url;
use function Sabatier\Foundation\unsafe_value;

/**
 * An object-oriented wrapper around PHP's native session subsystem.
 *
 * `Session` is managed automatically by `Responder` when `$isSessionEnabled` is `true`
 * (i.e. security is active but no JWT private key is configured). It is started before the
 * action runs and committed in the `finally` block of `Responder::$response`, so session
 * data is always flushed even if the action throws.
 *
 * ## Storage
 * Session files are written to a per-bundle subdirectory inside the system caches directory
 * (`SearchPathDirectory::cachesDirectory / bundleIdentifier / Session`). The directory is
 * created automatically on first access with full permissions.
 *
 * ## Stale-session cleanup
 * The destructor scans the storage directory and removes session files that either belong
 * to a different session ID or have exceeded the configured cookie lifetime. This keeps the
 * session directory from growing unboundedly without requiring a separate garbage-collection
 * process.
 *
 * ## KVC access
 * `valueForKey` and `setValueForKey` are overridden to proxy reads and writes through
 * `$_SESSION`, making session data accessible via the standard Foundation KVC interface.
 * Setting a key to `null` unsets it from the session.
 *
 * ## Cookie configuration
 * `$cookieParameters` controls the session cookie attributes (domain, path, lifetime,
 * secure, HTTP-only, SameSite). It defaults to a policy derived from the current request URL.
 *
 * @see CookieParameters
 * @see Responder
 */
final class Session extends ObjectClass
{
    /** @var bool Indicates whether the session is currently active and ready for data operations. */
    public bool $isActive {
        get => $this->status === SessionStatus::active;
    }
    /** @var URL The location where session data is stored. */
    private(set) URL $storageURL {
        /**
         * @throws Exception
         */
        get {
            if (!isset($this->storageURL)) {
                $this->storageURL = FileManager::default()->url(SearchPathDirectory::cachesDirectory)->appendingPathComponent(Bundle::main()->bundleIdentifier ?? ProcessInfo::processInfo()->processName)->appendingPathComponent("Session");
                if (!FileManager::default()->fileExists($this->storageURL->path)) {
                    FileManager::default()->createDirectory($this->storageURL, true, new Dictionary([FileAttributeKey::posixPermissions => 0777]));
                }
            }
            return $this->storageURL;
        }
    }
    /** @var CookieParameters The configuration used to initialize and manage the session's cookies, including domain, path, lifetime, secure, HTTP-only, and SameSite policy. */
    public CookieParameters $cookieParameters {
        get => $this->cookieParameters ??= new CookieParameters(parse_url(request_url(), PHP_URL_HOST) ?? "");
    }
    /** @var string The session id. */
    public string $id {
        get => unsafe_value(fn(): string => session_id());
    }
    /** @var string The session name. */
    public string $name {
        get => unsafe_value(fn(): string => session_name());
        set => unsafe_value(fn(): string => session_name($value));
    }
    /** @var SessionStatus The session status. */
    public SessionStatus $status {
        get => SessionStatus::from(unsafe_value(fn(): int => session_status()));
    }

    public function __destruct()
    {
        try {
            $id = $this->id;
            $lifetime = $this->cookieParameters->lifetime;
            $keys = new Set([URLResourceKey::creationDateKey, URLResourceKey::nameKey]);
            $urls = FileManager::default()->contentsOfDirectory($this->storageURL, new ArrayClass($keys), DirectoryEnumerationOptions::skipsHiddenFiles);
            foreach ($urls as $url) {
                $resourceValues = $url->resourceValues($keys);
                /** @var string $name */
                $name = $resourceValues->name;
                if (!str_starts_with($name, "sess_")) {
                    continue;
                }
                if (!str_ends_with($name, $id)) {
                    FileManager::default()->removeItem($url);
                    continue;
                }
                if (!$lifetime) {
                    continue;
                }
                /** @var Date $creationDate */
                $creationDate = $resourceValues->creationDate;
                $creationDate->addTimeInterval($lifetime);
                if ($creationDate->timeIntervalSinceNow > 0) {
                    continue;
                }
                FileManager::default()->removeItem($url);
            }
        } catch (Exception) {
        }
    }

    #[Override]
    public function valueForKey(string $key): mixed
    {
        return session_get($key);
    }

    #[Override]
    public function setValueForKey(mixed $value, string $key): void
    {
        if ($value === null) {
            session_unset_key($key);
        } else {
            session_set($key, $value);
        }
    }

    /**
     * Initialize session data
     */
    public function start(): void
    {
        session_start_with_params($this->cookieParameters, $this->storageURL->path);
    }

    /**
     * Closes the current session and writes session data
     */
    public function close(): void
    {
        session_write_close();
    }

    /**
     * Write session data and end the session
     */
    public function commit(): void
    {
        session_commit();
    }

    /**
     * Re-initialize the session with original values
     */
    public function reset(): void
    {
        unsafe_value(fn(): bool => session_reset());
    }

    /**
     * Discard changes and finish the session
     */
    public function invalidate(): void
    {
        session_destroy_safe();
    }

    /**
     * Update the current session id with a newly generated one
     */
    public function regenerateID(): void
    {
        session_regenerate_id_safe();
    }
}
