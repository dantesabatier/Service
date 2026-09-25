<?php

declare(strict_types=1);

namespace Sabatier\Service\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Sabatier\CoreData\EntityDescription;
use Sabatier\CoreData\FetchRequest;
use Sabatier\CoreData\ManagedObjectContext;
use Sabatier\CoreData\ManagedObjectModel;
use Sabatier\CoreData\PersistentStoreCoordinator;
use Sabatier\Foundation\ArrayClass;
use Sabatier\Foundation\ObjectClass;
use Sabatier\Foundation\Predicates\ComparisonPredicate;
use Sabatier\Foundation\Predicates\CompoundPredicate;
use Sabatier\Service\Request;
use Sabatier\Service\RequestToFetchRequestAdapter;

final class RequestToFetchRequestAdapterTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = $_SERVER;
        ObjectClass::$staticAssociatedValues = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        ObjectClass::$staticAssociatedValues = [];
        parent::tearDown();
    }

    /** @throws ReflectionException */
    #[Test]
    public function aQueryWithoutParametersYieldsAnUnfilteredRequest(): void
    {
        $fetchRequest = $this->adapt("/Order");
        $this->assertNull($fetchRequest->predicate);
        $this->assertNull($fetchRequest->serialization?->keys->first);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aSingleQueryParameterBecomesOneEqualityPredicate(): void
    {
        $fetchRequest = $this->adapt("/Order?status=active");
        $this->assertSame("status = 'active'", $this->predicateFormat($fetchRequest));
        $this->assertInstanceOf(ComparisonPredicate::class, $fetchRequest->predicate);
    }

    /** @throws ReflectionException */
    #[Test]
    public function severalQueryParametersAreAndedTogether(): void
    {
        $fetchRequest = $this->adapt("/Order?status=active&city=Paris");
        $format = $this->predicateFormat($fetchRequest);
        $this->assertStringContainsString("status = 'active'", $format);
        $this->assertStringContainsString("AND", $format);
        $this->assertStringContainsString("city = 'Paris'", $format);
        $this->assertInstanceOf(CompoundPredicate::class, $fetchRequest->predicate);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aBase64FetchRequestReplacesTheWholeRequest(): void
    {
        $fetchRequest = $this->adapt("/Order?fetchRequest=" . $this->encoded("{\"entityName\": \"Order\", \"fetchLimit\": 7, \"predicate\": {\"format\": \"status == %@\", \"arguments\": [\"paid\"]}}"), null, $this->contextForModelWithEntityNamed("Order"));
        $this->assertSame("Order", $fetchRequest->entityName);
        $this->assertSame(7, $fetchRequest->fetchLimit);
        $this->assertStringContainsString("paid", $this->predicateFormat($fetchRequest));
    }

    /** @throws ReflectionException */
    #[Test]
    public function theFetchRequestKeyIsMatchedWithoutRegardToCase(): void
    {
        $this->assertSame(7, $this->adapt("/Order?FETCHREQUEST=" . $this->encoded("{\"fetchLimit\": 7}"))->fetchLimit);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theFetchRequestKeySuppressesTheEqualityPredicates(): void
    {
        $this->assertNull($this->adapt("/Order?status=active&fetchRequest=" . $this->encoded("{\"fetchLimit\": 7}"))->predicate);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anEmptyFetchRequestValueLeavesAnUntouchedRequest(): void
    {
        $fetchRequest = $this->adapt("/Order?fetchRequest=");
        $this->assertNull($fetchRequest->predicate);
        $this->assertSame(0, $fetchRequest->fetchLimit);
    }

    /** @throws ReflectionException */
    #[Test]
    public function aFetchRequestValueThatIsNotBase64JSONLeavesAnUntouchedRequest(): void
    {
        $this->assertSame(0, $this->adapt("/Order?fetchRequest=" . base64_encode("{not json"))->fetchLimit);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theSerializationHeaderIsCarriedOntoTheRequest(): void
    {
        $fetchRequest = $this->adapt("/Order", "{\"name\": true}");
        $this->assertSame(["name"], $fetchRequest->serialization->keys->array);
    }

    /** @throws ReflectionException */
    #[Test]
    public function theSerializationHeaderSurvivesABase64FetchRequest(): void
    {
        $fetchRequest = $this->adapt("/Order?fetchRequest=" . $this->encoded("{\"fetchLimit\": 7}"), "{\"name\": true}");
        $this->assertSame(7, $fetchRequest->fetchLimit);
        $this->assertSame(["name"], $fetchRequest->serialization->keys->array);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anEntityNameIsResolvedAgainstTheModel(): void
    {
        $fetchRequest = $this->adapt("/Order?fetchRequest=" . $this->encoded("{\"entityName\": \"Order\"}"), null, $this->contextForModelWithEntityNamed("Order"));
        $this->assertSame("Order", $fetchRequest->entity?->name);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anEntityNameAbsentFromTheModelResolvesToNothing(): void
    {
        $fetchRequest = $this->adapt("/Order?fetchRequest=" . $this->encoded("{\"entityName\": \"Ghost\"}"), null, $this->contextForModelWithEntityNamed("Order"));
        $this->assertNull($fetchRequest->entity);
    }

    /** @throws ReflectionException */
    #[Test]
    public function anUnresolvableEntityNameIsClearedRatherThanKept(): void
    {
        // Assigning FetchRequest::$entity writes $entityName back from the description, so a lookup that finds nothing leaves the name empty instead of the one the caller sent.
        $this->assertNull($this->adapt("/Order?fetchRequest=" . $this->encoded("{\"entityName\": \"Ghost\"}"), null, $this->contextForModelWithEntityNamed("Order"))->entityName);
    }

    /** @throws ReflectionException */
    #[Test]
    public function withoutAnEntityNameTheModelIsNeverConsulted(): void
    {
        $this->assertNull($this->adapt("/Order?status=active", null, $this->contextForModelWithEntityNamed("Order"))->entity);
    }

    private function encoded(string $json): string
    {
        return base64_encode($json);
    }

    private function predicateFormat(FetchRequest $fetchRequest): string
    {
        $this->assertNotNull($fetchRequest->predicate);
        return $fetchRequest->predicate->predicateFormat;
    }

    /** @throws ReflectionException */
    private function contextForModelWithEntityNamed(string $name): ManagedObjectContext
    {
        $entity = new EntityDescription();
        $entity->name = $name;
        $model = new ManagedObjectModel();
        $model->entities = new ArrayClass([$entity]);
        $context = new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor();
        $context->persistentStoreCoordinator = new PersistentStoreCoordinator($model);
        return $context;
    }

    /** @throws ReflectionException */
    private function adapt(string $requestURI, ?string $serialization = null, ?ManagedObjectContext $context = null): FetchRequest
    {
        $_SERVER["HTTP_HOST"] = "localhost";
        $_SERVER["REQUEST_URI"] = $requestURI;
        $_SERVER["REQUEST_METHOD"] = "GET";
        if ($serialization === null) {
            unset($_SERVER["HTTP_SERIALIZATION"]);
        } else {
            $_SERVER["HTTP_SERIALIZATION"] = $serialization;
        }
        return new RequestToFetchRequestAdapter(new Request(), $context ?? new ReflectionClass(ManagedObjectContext::class)->newInstanceWithoutConstructor())->fetchRequest;
    }
}
