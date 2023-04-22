<?php

namespace Sabatier\Service;

use ArrayAccess;
use Exception;
use JetBrains\PhpStorm\ExpectedValues;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPCookiePropertyKey;
use Sabatier\Foundation\Networking\HTTPCookieStringPolicy;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLResourceKey;
use function Sabatier\Foundation\unsafe_value;

/**
 * An object-oriented wrapper for a session.
 * @property-read string|null $id
 * @property-read SessionStatus $status
 * @property string|null $user
 * @implements ArrayAccess<string, mixed>
 */
class Session extends ObjectClass implements ArrayAccess
{
    public URL $saveURL;

    public function __construct(public readonly string $domain, public readonly string $path = "/", public readonly int $lifetime = 0, public readonly bool $isSecure = true, public readonly bool $isHTTPOnly = true, #[ExpectedValues(valuesFromClass: HTTPCookieStringPolicy::class)] public readonly string $sameSitePolicy = HTTPCookieStringPolicy::sameSiteLax)
    {
        unset($this->saveURL);
    }

    public function __destruct()
    {
        /** @noinspection PhpArrayKeyDoesNotMatchArrayShapeInspection */
        if (!($timeInterval = session_get_cookie_params()[HTTPCookiePropertyKey::lifetime]) || !($sessionID = session_id())) {
            return;
        }
        $keys = new ArrayClass([URLResourceKey::creationDateKey, URLResourceKey::nameKey]);
        $urls = FileManager::default()->contentsOfDirectory($this->saveURL);
        foreach ($urls as $url) {
            try {
                $resourceValues = $url->resourceValues(new Set($keys));
                /** @var string $name */
                $name = $resourceValues->name;
                if (!str_starts_with($name, "sess_")) {
                    continue;
                }
                if (!str_ends_with($name, $sessionID)) {
                    FileManager::default()->removeItem($url);
                    continue;
                }
                /** @var Date $creationDate */
                $creationDate = $resourceValues->creationDate;
                $creationDate->addTimeInterval($timeInterval);
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
        if ($name == "saveURL") {
            $saveURL = FileManager::default()->url(SearchPathDirectory::cachesDirectory)->appendingPathComponent(Bundle::main()->bundleIdentifier ?? ProcessInfo::processInfo()->processName);
            if (!FileManager::default()->fileExists($saveURL->path)) {
                FileManager::default()->createDirectory($saveURL, true);
            }
            $this->$name = $saveURL;
            return $this->$name;
        } elseif ($name == "id") {
            if (!($id = session_id())) {
                return null;
            }
            return $id;
        } elseif ($name == "status") {
            return SessionStatus::from(session_status());
        } else {
            return $this->valueForKey($name);
        }
    }

    public function __set(string $name, mixed $value): void
    {
        if ($name == "user") {
            $this->setValueForKey($value, $name);
        } else {
            $this->setValueForUndefinedKey($value, $name);
        }
    }

    public function valueForKey(string $key): mixed
    {
        return $this->offsetGet($key);
    }

    public function setValueForKey(mixed $value, string $key): void
    {
        $this->offsetSet($value, $key);
    }

    /**
     * Initialize session data
     * @throws Exception
     */
    public function start(): void
    {
        unsafe_value(function (): bool {
            /** @psalm-suppress InvalidArgument */
            session_set_cookie_params([
                HTTPCookiePropertyKey::domain => $this->domain,
                HTTPCookiePropertyKey::path => $this->path,
                HTTPCookiePropertyKey::lifetime => $this->lifetime,
                HTTPCookiePropertyKey::secure => $this->isSecure,
                HTTPCookiePropertyKey::httpOnly => $this->isHTTPOnly,
                HTTPCookiePropertyKey::sameSitePolicy => $this->sameSitePolicy,
            ]);
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

    /**
     * @param string $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($_SESSION[$offset]);
    }

    /**
     * @param string $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $_SESSION[$offset] ?? null;
    }

    /**
     * @param string $offset
     * @param mixed $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($value === null) {
            $this->offsetUnset($offset);
            return;
        }
        $_SESSION[$offset] = $value;
    }

    /**
     * @param string $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        if (!$this->offsetExists($offset)) {
            return;
        }
        unset($_SESSION[$offset]);
    }
}
