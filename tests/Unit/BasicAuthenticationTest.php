<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\Foundation\Dictionary;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Service\AuthenticationContext;
use Sabatier\Service\AuthenticationService;
use Sabatier\Service\AuthorizationHeader;
use Sabatier\Service\BasicAuthentication;

final class BasicAuthenticationTest extends TestCase
{
    /** @throws ReflectionException */
    #[Test]
    public function derivesTheCredentialFromTheDecodedHeader(): void
    {
        $credential = $this->authentication(base64_encode("mañana:secret"))->credential;
        $this->assertSame("mañana", $credential?->user);
        $this->assertSame("secret", $credential?->password);
    }

    /** @throws ReflectionException */
    #[Test]
    public function thereIsNoCredentialWithoutTheDelimiter(): void
    {
        $this->assertNull($this->authentication(base64_encode("ada"))->credential);
    }

    /** @throws ReflectionException */
    #[Test]
    public function thereIsNoCredentialForAHeaderThatIsNotUTF8(): void
    {
        $this->assertNull($this->authentication(base64_encode("ma\xF1ana:secret"))->credential);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aHeaderThatIsNotUTF8IsNotValid(): void
    {
        $this->assertFalse($this->authentication(base64_encode("ma\xF1ana:secret"))->isValid);
    }

    /** @throws ReflectionException */
    private function authentication(string $headerValue): BasicAuthentication
    {
        $context = new AuthenticationContext(
            new AuthorizationHeader("Basic $headerValue"),
            HTTPRequestMethod::get,
            new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor(),
            null,
            new ReflectionClass(AuthenticationService::class)->newInstanceWithoutConstructor()
        );
        return new BasicAuthentication($context, new Dictionary());
    }
}
