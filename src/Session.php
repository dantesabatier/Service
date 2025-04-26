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
 * An object-oriented wrapper for a session.
 */
class Session extends ObjectClass
{
    public CookieParameters $cookieParameters {
        get => $this->cookieParameters ??= new CookieParameters(parse_url(request_url(), PHP_URL_HOST) ?? "");
    }
    /** @var URL The url to save the session. */
    public URL $saveURL {
        get {
            if (!isset($this->saveURL)) {
                $this->saveURL = FileManager::default()->url(SearchPathDirectory::cachesDirectory)->appendingPathComponent(Bundle::main()->bundleIdentifier ?? ProcessInfo::processInfo()->processName)->appendingPathComponent("Session");
                if (!FileManager::default()->fileExists($this->saveURL->path)) {
                    FileManager::default()->createDirectory($this->saveURL, true, new Dictionary([FileAttributeKey::posixPermissions => 0777]));
                }
            }
            return $this->saveURL;
        }
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
        $id = $this->id;
        $lifetime = $this->cookieParameters->lifetime;
        $keys = new Set([URLResourceKey::creationDateKey, URLResourceKey::nameKey]);
        $urls = FileManager::default()->contentsOfDirectory($this->saveURL, new ArrayClass($keys), DirectoryEnumerationOptions::skipsHiddenFiles);
        foreach ($urls as $url) {
            try {
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
            } catch (Exception) {
            }
        }
    }

    #[Override]
    public function valueForKey(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    #[Override]
    public function setValueForKey(mixed $value, string $key): void
    {
        if ($value === null) {
            unset($_SESSION[$key]);
        } else {
            $_SESSION[$key] = $value;
        }
    }

    /**
     * Initialize session data
     */
    public function start(): void
    {
        unsafe_value(function (): bool {
            session_set_cookie_params($this->cookieParameters->allValues);
            session_save_path($this->saveURL->path);
            return session_start();
        });
    }

    /**
     * Write session data and end the session
     */
    public function commit(): void
    {
        unsafe_value(fn(): bool => session_commit());
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
        unsafe_value(fn(): bool => session_abort());
    }

    /**
     * Update the current session id with a newly generated one
     */
    public function regenerateID(): void
    {
        unsafe_value(fn(): bool => session_regenerate_id());
    }
}
