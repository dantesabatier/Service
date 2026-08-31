<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Service\FileTransferComponent;

/**
 * A component is the whole of the containment the transfer surface relies on: `documentRoot/{directory}/{filename}`
 * stays inside the document root only because neither name can name a location. These cases are the invariant.
 *
 * A name is refused for what it could reach, never for how it reads, so the second set is as important as the
 * first: spaces, accents and second dots all name a file inside the directory like any other character.
 */
final class FileTransferComponentTest extends TestCase
{
    /** @return list<array{string}> */
    public static function namesThatCouldDescribeAnotherLocation(): array
    {
        return [["../afuera.txt"], ["../../etc/passwd"], ["sub/../../afuera.txt"], ["dir/file.txt"], ["..\\afuera.txt"], ["/etc/passwd"], ["."], [".."], [".htaccess"], [".hidden.txt"], [""], ["   "], ["a\0b"]];
    }

    #[Test]
    #[DataProvider("namesThatCouldDescribeAnotherLocation")]
    public function aNameThatCouldDescribeAnotherLocationIsInvalid(string $value): void
    {
        $this->assertFalse(new FileTransferComponent($value)->isValid, "\"$value\" must not be treated as a plain component.");
    }

    /** @return list<array{string}> */
    public static function plainNames(): array
    {
        return [["invoice.pdf"], ["invoice"], ["report-2026.csv"], ["a_b-c.TXT"], ["uploads"], ["9"], ["RAYA DOM_EL PALACIO DE HIERRO_6074_4519192040.csv"], ["Diseño.csv"], ["respaldo.tar.gz"], ["a..b"], ["a.b.c"], ["invoice . pdf"]];
    }

    #[Test]
    #[DataProvider("plainNames")]
    public function aPlainNameIsValid(string $value): void
    {
        $this->assertTrue(new FileTransferComponent($value)->isValid, "\"$value\" must be accepted as a plain component.");
    }

    /** Surrounding whitespace is the one thing settled on the way in, so a name is compared and stored as the same string. */
    #[Test]
    public function surroundingWhitespaceIsTrimmed(): void
    {
        $component = new FileTransferComponent("  invoice.pdf  ");

        $this->assertSame("invoice.pdf", $component->value);
        $this->assertTrue($component->isValid);
    }

    /** Nothing else is rewritten: a refused name stays readable so the caller can name it in the failure it reports, rather than being reduced to something the client never sent. */
    #[Test]
    public function aRefusedNameIsKeptAsItArrived(): void
    {
        $component = new FileTransferComponent("../../etc/passwd");

        $this->assertSame("../../etc/passwd", $component->value);
        $this->assertFalse($component->isValid);
    }

    /** One path component cannot exceed 255 bytes on any common filesystem, so a longer name is refused before the filesystem is asked. */
    #[Test]
    public function aNameLongerThanAPathComponentIsInvalid(): void
    {
        $this->assertTrue(new FileTransferComponent(str_repeat("a", 255))->isValid);
        $this->assertFalse(new FileTransferComponent(str_repeat("a", 256))->isValid);
    }
}
