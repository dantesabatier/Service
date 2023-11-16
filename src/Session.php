<?php

namespace Sabatier\Service;

use Exception;
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
 * @property-read string $id The session id.
 * @property string $name The session name.
 * @property-read SessionStatus $status The session status.
 */
class Session extends ObjectClass
{
    public CookieParameters $cookieParameters;
    /** @var URL The session save url. */
    public URL $saveURL;

    public function __construct()
    {
        unset($this->cookieParameters);
        unset($this->saveURL);
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

    /**
     * @throws Exception
     */
    public function __get(string $name)
    {
        if ($name == "id") {
            return unsafe_value(fn(): string => session_id());
        } elseif ($name == "name") {
            return unsafe_value(fn(): string => session_name());
        } elseif ($name == "status") {
            return SessionStatus::from(unsafe_value(fn(): int => session_status()));
        } elseif ($name == "cookieParameters") {
            $this->$name = new CookieParameters((string)parse_url(request_url(), PHP_URL_HOST));
            return $this->$name;
        } elseif ($name == "saveURL") {
            $saveURL = FileManager::default()->url(SearchPathDirectory::cachesDirectory)->appendingPathComponent(Bundle::main()->bundleIdentifier ?? ProcessInfo::processInfo()->processName)->appendingPathComponent("Session");
            if (!FileManager::default()->fileExists($saveURL->path)) {
                FileManager::default()->createDirectory($saveURL, true, new Dictionary([FileAttributeKey::posixPermissions => 0777]));
            }
            $this->$name = $saveURL;
            return $this->$name;
        } else {
            return $this->valueForUndefinedKey($name);
        }
    }

    /**
     * @throws Exception
     */
    public function __set(string $name, mixed $value): void
    {
        if ($name == "saveURL" || $name == "cookieParameters") {
            $this->$name = $value;
        } elseif ($name == "name") {
            unsafe_value(fn(): string => session_name($value));
        } else {
            $this->setValueForUndefinedKey($value, $name);
        }
    }

    public function valueForKey(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

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
     * @throws Exception
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
     * Write session data and end session
     * @throws Exception
     */
    public function commit(): void
    {
        unsafe_value(fn(): bool => session_commit());
    }

    /**
     * Re-initialize session with original values
     * @throws Exception
     */
    public function reset(): void
    {
        unsafe_value(fn(): bool => session_reset());
    }

    /**
     * Discard changes and finish session
     * @throws Exception
     */
    public function invalidate(): void
    {
        unsafe_value(fn(): bool => session_abort());
    }

    /**
     * Update the current session id with a newly generated one
     * @throws Exception
     */
    public function regenerateID(): void
    {
        unsafe_value(fn(): bool => session_regenerate_id());
    }
}
