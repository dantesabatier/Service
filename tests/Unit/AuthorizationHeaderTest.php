<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Service\AuthenticationScheme;
use Sabatier\Service\AuthorizationHeader;

final class AuthorizationHeaderTest extends TestCase
{
    // --- Standard scheme parsing ---

    #[Test]
    public function bearerSchemeNameValueAndScheme(): void
    {
        $h = new AuthorizationHeader('Bearer abc123');
        $this->assertSame('Bearer', $h->name);
        $this->assertSame('abc123', $h->value);
        $this->assertSame(AuthenticationScheme::bearer, $h->scheme);
    }

    #[Test]
    public function basicSchemeNameValueAndScheme(): void
    {
        $h = new AuthorizationHeader('Basic dXNlcjpwYXNz');
        $this->assertSame('Basic', $h->name);
        $this->assertSame('dXNlcjpwYXNz', $h->value);
        $this->assertSame(AuthenticationScheme::basic, $h->scheme);
    }

    #[Test]
    public function digestSchemeNameValueAndScheme(): void
    {
        $h = new AuthorizationHeader('Digest realm="example.com", nonce="abc"');
        $this->assertSame('Digest', $h->name);
        $this->assertSame('realm="example.com", nonce="abc"', $h->value);
        $this->assertSame(AuthenticationScheme::digest, $h->scheme);
    }

    #[Test]
    public function negotiateSchemeIsRecognized(): void
    {
        $h = new AuthorizationHeader('Negotiate token');
        $this->assertSame('Negotiate', $h->name);
        $this->assertSame(AuthenticationScheme::negotiate, $h->scheme);
    }

    // --- Case normalization ---

    #[Test]
    public function lowercaseSchemeNormalizesToUcfirst(): void
    {
        $h = new AuthorizationHeader('bearer TOKEN');
        $this->assertSame('Bearer', $h->name);
        $this->assertSame(AuthenticationScheme::bearer, $h->scheme);
    }

    #[Test]
    public function uppercaseSchemeNormalizesToUcfirst(): void
    {
        $h = new AuthorizationHeader('BASIC creds');
        $this->assertSame('Basic', $h->name);
        $this->assertSame(AuthenticationScheme::basic, $h->scheme);
    }

    // --- AWS special case ---

    #[Test]
    public function awsSchemePreservesFullCase(): void
    {
        $h = new AuthorizationHeader('AWS4-HMAC-SHA256 Credential=abc/date/region/s3/aws4_request');
        $this->assertSame('AWS4-HMAC-SHA256', $h->name);
        $this->assertSame(AuthenticationScheme::aws, $h->scheme);
    }

    #[Test]
    public function awsSchemeMatchesCaseInsensitively(): void
    {
        $h = new AuthorizationHeader('aws4-hmac-sha256 Credential=abc');
        $this->assertSame('AWS4-HMAC-SHA256', $h->name);
        $this->assertSame(AuthenticationScheme::aws, $h->scheme);
    }

    // --- Unknown scheme ---

    #[Test]
    public function unknownSchemeFallsBackToBasicScheme(): void
    {
        $h = new AuthorizationHeader('Custom token123');
        $this->assertSame('Custom', $h->name);
        $this->assertSame(AuthenticationScheme::basic, $h->scheme);
    }

    // --- rawValue ---

    #[Test]
    public function rawValueIsPreserved(): void
    {
        $raw = 'Bearer abc123';
        $this->assertSame($raw, (new AuthorizationHeader($raw))->rawValue);
    }

    // --- Value with internal spaces ---

    #[Test]
    public function valueContainingSpacesIsKeptIntact(): void
    {
        $h = new AuthorizationHeader('Bearer part1 part2 part3');
        $this->assertSame('part1 part2 part3', $h->value);
    }

    // --- Edge cases ---

    #[Test]
    public function emptyInputDefaultsToBasicWithEmptyValue(): void
    {
        $h = new AuthorizationHeader('');
        $this->assertSame(AuthenticationScheme::basic, $h->scheme);
        $this->assertSame('', $h->value);
    }

    #[Test]
    public function schemeWithoutValueDefaultsToBasic(): void
    {
        // single word — preg_split produces only 1 component → fallback
        $h = new AuthorizationHeader('Bearer');
        $this->assertSame(AuthenticationScheme::basic, $h->scheme);
        $this->assertSame('', $h->value);
    }
}
