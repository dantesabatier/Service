<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sabatier\Foundation\Networking\HTTPRequestMethod;
use Sabatier\Service\Action;
use Sabatier\Service\Endpoint;
use Sabatier\Service\JSONTransformer;
use Sabatier\Service\NoCacheHeaderTransformer;
use Sabatier\Service\ResponderResolution;
use Sabatier\Service\ResponseHeaderSanitizerTransformer;

// --- Fixtures ---

#[Endpoint('/products')]
final class ProductsFixture {}

#[Endpoint]
final class ArticlesFixture {}

#[Endpoint('orders')]
final class OrdersFixture {}

#[Endpoint('/users', transformers: [JSONTransformer::class])]
final class UsersFixture
{
    #[Action(method: HTTPRequestMethod::post)]
    public function create(): void {}

    #[Action(method: HTTPRequestMethod::patch, path: '/users/profile')]
    public function updateProfile(): void {}

    #[Action(method: HTTPRequestMethod::delete, transformers: [NoCacheHeaderTransformer::class])]
    public function delete(): void {}
}

final class NoAttributeFixture {}

// --- Tests ---

final class ResponderResolutionTest extends TestCase
{
    // --- #[Endpoint] matching ---

    #[Test]
    public function matchesTrueForExplicitEndpointPath(): void
    {
        $r = new ResponderResolution(ProductsFixture::class, '/products');
        $this->assertTrue($r->matches);
    }

    #[Test]
    public function matchesFalseForDifferentPath(): void
    {
        $r = new ResponderResolution(ProductsFixture::class, '/orders');
        $this->assertFalse($r->matches);
    }

    #[Test]
    public function defaultPathUsesClassShortName(): void
    {
        $r = new ResponderResolution(ArticlesFixture::class, '/ArticlesFixture');
        $this->assertTrue($r->matches);
    }

    #[Test]
    public function pathWithoutLeadingSlashGetsSlashPrepended(): void
    {
        $r = new ResponderResolution(OrdersFixture::class, '/orders');
        $this->assertTrue($r->matches);
    }

    #[Test]
    public function endpointMatchingIsCaseInsensitive(): void
    {
        $r = new ResponderResolution(ProductsFixture::class, '/PRODUCTS');
        $this->assertTrue($r->matches);
    }

    #[Test]
    public function selectorIsNullForEndpointMatch(): void
    {
        $r = new ResponderResolution(ProductsFixture::class, '/products');
        $this->assertNull($r->selector);
    }

    #[Test]
    public function transformersFromEndpointAreCollected(): void
    {
        $r = new ResponderResolution(UsersFixture::class, '/users');
        $this->assertTrue($r->transformers->contains(fn(string $t) => $t === JSONTransformer::class));
    }

    // --- #[Action] matching ---

    #[Test]
    public function matchesTrueForImplicitActionPath(): void
    {
        $r = new ResponderResolution(UsersFixture::class, '/create');
        $this->assertTrue($r->matches);
    }

    #[Test]
    public function selectorIsMethodNameForActionMatch(): void
    {
        $r = new ResponderResolution(UsersFixture::class, '/create');
        $this->assertSame('create', $r->selector);
    }

    #[Test]
    public function actionMatchingIsCaseInsensitive(): void
    {
        $r = new ResponderResolution(UsersFixture::class, '/CREATE');
        $this->assertTrue($r->matches);
    }

    #[Test]
    public function actionWithExplicitPathMatches(): void
    {
        $r = new ResponderResolution(UsersFixture::class, '/users/profile');
        $this->assertTrue($r->matches);
        $this->assertSame('updateProfile', $r->selector);
    }

    #[Test]
    public function transformersFromActionAreCollected(): void
    {
        $r = new ResponderResolution(UsersFixture::class, '/delete');
        $this->assertTrue($r->transformers->contains(fn(string $t) => $t === NoCacheHeaderTransformer::class));
    }

    // --- Sin atributos ---

    #[Test]
    public function matchesFalseWhenNoAttributes(): void
    {
        $r = new ResponderResolution(NoAttributeFixture::class, '/no-attribute-fixture');
        $this->assertFalse($r->matches);
    }

    #[Test]
    public function selectorIsNullWhenNoMatch(): void
    {
        $r = new ResponderResolution(NoAttributeFixture::class, '/anything');
        $this->assertNull($r->selector);
    }

    // --- ResponseHeaderSanitizerTransformer siempre presente ---

    #[Test]
    public function sanitizerTransformerAlwaysPresentOnMatch(): void
    {
        $r = new ResponderResolution(ProductsFixture::class, '/products');
        $this->assertTrue($r->transformers->contains(fn(string $t) => $t === ResponseHeaderSanitizerTransformer::class));
    }

    #[Test]
    public function sanitizerTransformerAlwaysPresentOnNoMatch(): void
    {
        $r = new ResponderResolution(NoAttributeFixture::class, '/anything');
        $this->assertTrue($r->transformers->contains(fn(string $t) => $t === ResponseHeaderSanitizerTransformer::class));
    }
}
