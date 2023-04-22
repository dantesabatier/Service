<?php

namespace Sabatier\Service;

use ArrayAccess;
use Exception;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\Bundle;
use Sabatier\Foundation\Date;
use Sabatier\Foundation\FileManager;
use Sabatier\Foundation\Networking\HTTPCookiePropertyKey;
use Sabatier\Foundation\ProcessInfo;
use Sabatier\Foundation\SearchPathDirectory;
use Sabatier\Foundation\Set;
use Sabatier\Foundation\URL;
use Sabatier\Foundation\URLResourceKey;
use function Sabatier\Foundation\unsafe_value;

/**
 * @property-read SessionState $state
 * @implements ArrayAccess<string, mixed>
 */
class Session implements ArrayAccess
{
    public URL $sessionSaveURL;

    /**
     * @param array<string, mixed> $cookieParams
     */
    public function __construct(public readonly array $cookieParams)
    {
        unset($this->sessionSaveURL);
    }

    public function __destruct()
    {
        /** @noinspection PhpArrayKeyDoesNotMatchArrayShapeInspection */
        if (!($timeInterval = session_get_cookie_params()[HTTPCookiePropertyKey::lifetime]) || !($sessionID = session_id())) {
            return;
        }
        $keys = new ArrayClass([URLResourceKey::creationDateKey, URLResourceKey::nameKey]);
        $urls = FileManager::default()->contentsOfDirectory($this->sessionSaveURL);
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
        if ($name == "sessionSaveURL") {
            $sessionSaveURL = FileManager::default()->url(SearchPathDirectory::cachesDirectory)->appendingPathComponent(Bundle::main()->bundleIdentifier ?? ProcessInfo::processInfo()->processName);
            if (!FileManager::default()->fileExists($sessionSaveURL->path)) {
                FileManager::default()->createDirectory($sessionSaveURL, true);
            }
            $this->$name = $sessionSaveURL;
            return $this->$name;
        } elseif ($name == "state") {
            return SessionState::from(session_status());
        } else {
            return $this[$name];
        }
    }

    /**
     * @throws Exception
     */
    public function start(): void
    {
        unsafe_value(function (): bool {
            /** @psalm-suppress ArgumentTypeCoercion */
            session_set_cookie_params($this->cookieParams);
            session_save_path($this->sessionSaveURL->path);
            return session_start();
        });
    }

    /**
     * @throws Exception
     */
    public function close(): void
    {
        unsafe_value(fn(): bool => session_write_close());
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
