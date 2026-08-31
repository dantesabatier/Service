<?php

declare(strict_types=1);

namespace Sabatier\Service;

use Sabatier\Foundation\ArrayClass;

/**
 * One component of a transfer location — a subdirectory name or a filename — checked against the one thing that would let it describe somewhere else.
 *
 * A component is the whole of the containment the transfer surface relies on. `documentRoot/{directory}/{filename}` is inside the document root by construction as long as neither name can name a location: no path separator, no dot component, no NUL, nothing empty. Nothing then has to be canonicalized or prefix-compared after the fact, which is the failure mode this replaces — a guard that compared the prefix of a string it had not resolved yet.
 *
 * What a component may otherwise contain is deliberately not restricted. A name is refused for what it could reach, never for how it reads: a space, an accent or a second dot name the same file as any other character, and an allowlist of ASCII word characters would refuse `RAYA DOM_EL PALACIO DE HIERRO.csv` and `respaldo.tar.gz` while buying no containment the rules below do not already give. What may be uploaded at all is a question of authorization, which the endpoint answers behind the same RBAC as the rest of the surface.
 *
 * A leading dot is refused, which settles `.` and `..` along with every dotfile. The two dot components have to go: they resolve to a directory rather than to something inside one. The rest of the dotfiles are the one restriction here that is not about reaching elsewhere — `.htaccess` names nothing outside its directory, and a download may well ask for one, since {@see StaticResourcePolicy} already decides whether it is served. An upload has nothing behind it, and writing that name into a served directory has consequences that are not traversal but are still consequences.
 *
 * `$isValid` is decided once, when the value is set, the way {@see AuthorizationHeader} settles a header it was handed. Nothing is ever rewritten: an invalid component stays available through `$value` so a caller can name it in the failure it reports. Reducing `../../etc/passwd` to `passwd` would store a file nobody asked for, and a refusal is easier to explain than a silent substitution.
 */
final class FileTransferComponent
{
    /** @var int The longest a single component may be, matching the limit every common filesystem imposes on one path component. */
    private const int maximumLength = 255;
    /** @var list<string> The sequences that would let a component reach outside the directory it belongs to: either separator, and the byte that truncates a name on the way to the filesystem. */
    private const array reservedSequences = ["/", "\\", "\0"];

    /** @var string The component as it arrived, trimmed of surrounding whitespace and never otherwise altered. */
    private(set) string $value {
        set => trim($value);
    }
    /** @var bool Whether the component can only name something inside the directory it belongs to. */
    public bool $isValid {
        get {
            if (isset($this->isValid)) {
                return $this->isValid;
            }
            $value = $this->value;
            return $this->isValid = $value !== ""
                && mb_strlen($value) <= self::maximumLength
                && !str_starts_with($value, ".")
                && !new ArrayClass(self::reservedSequences)->contains(fn(string $sequence): bool => str_contains($value, $sequence));
        }
    }

    /** @param string $value The name as it arrived from the request, unchecked. */
    public function __construct(string $value)
    {
        $this->value = $value;
    }
}
