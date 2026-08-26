<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;

/**
 * One component of a transfer location — a subdirectory name or a filename — checked against the one shape that cannot describe anywhere else.
 *
 * A component is the whole of the containment the transfer surface relies on. `documentRoot/{directory}/{filename}` is inside the document root by construction as long as neither name can name a location: no path separator, no dot component, nothing but letters, digits, underscore and hyphen. Nothing then has to be canonicalized or prefix-compared after the fact, which is the failure mode this replaces — a guard that compared the prefix of a string it had not resolved yet.
 *
 * `$isValid` is decided once, when the value is set, the way {@see AuthorizationHeader} settles a header it was handed. Nothing is ever rewritten: an invalid component stays available through `$value` so a caller can name it in the failure it reports. Reducing `../../etc/passwd` to `passwd` would store a file nobody asked for, and a refusal is easier to explain than a silent substitution.
 */
final class FileTransferComponent
{
    /** @var int The longest a single component may be, matching the limit every common filesystem imposes on one path component. */
    private const int maximumLength = 255;
    /** @var string The one shape a component may take. */
    private const string pattern = "/^[A-Za-z0-9_-]+$/";

    /** @var string The component as it arrived, trimmed of surrounding whitespace and never otherwise altered. */
    private(set) string $value {
        set => trim($value);
    }
    /** @var bool Whether the component can only name something inside the directory it belongs to, its stem and its extension each held to the same shape. */
    public bool $isValid {
        get {
            if (isset($this->isValid)) {
                return $this->isValid;
            }
            // A plain explode(...), where configured lists are read with string_split_trimmed: that helper drops empty pieces, which is right for a setting and wrong here, since an empty piece is exactly the evidence a name is not plain. Dropping them would reduce ".htaccess" to one valid-looking piece, and "a..b" and "invoice . pdf" to names the client never sent.
            $pieces = new ArrayClass(explode(".", $this->value));
            return $this->isValid = mb_strlen($this->value) <= self::maximumLength && $pieces->count <= 2 && $pieces->allSatisfy(fn(string $piece): bool => preg_match(self::pattern, $piece) === 1);
        }
    }

    /** @param string $value The name as it arrived from the request, unchecked. */
    public function __construct(string $value)
    {
        $this->value = $value;
    }
}
