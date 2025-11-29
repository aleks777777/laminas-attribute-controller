<?php

declare(strict_types=1);

namespace Tests\Validation;

use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Laminas\Http\Request;
use Laminas\Router\RouteMatch;
use LaminasAttributeController\ResolutionContext;
use LaminasAttributeController\Validation\ApiSymfonyValidatorChainException;
use LaminasAttributeController\Validation\MapRequestPayload;
use LaminasAttributeController\Validation\MapRequestPayloadResolver;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Validator\Validation;
use TypeError;

final class MapRequestPayloadResolverTest extends TestCase
{
    /**
     * @throws \ReflectionException
     */
    public function testConvertsTypeErrorIntoValidationException(): void
    {
        $request = new Request();
        $request->getHeaders()->addHeaderLine('Content-Type', 'application/json');
        $request->setContent('{"name":null}');

        $serializer = new class () implements SerializerInterface {
            public function serialize($data, string $format, ?SerializationContext $context = null, ?string $type = null): string
            {
                return '';
            }

            public function deserialize(string $data, string $type, string $format, ?DeserializationContext $context = null)
            {
                throw new TypeError('Cannot assign null to property Tests\\Validation\\Dto\\SamplePayload::$name of type string');
            }
        };

        $resolver = new MapRequestPayloadResolver($serializer, Validation::createValidator(), $request);
        $controller = new class () {
            public function action(#[MapRequestPayload] object $payload): void
            {
            }
        };

        $parameter = (new ReflectionMethod($controller, 'action'))->getParameters()[0];
        $context = new ResolutionContext($parameter, new RouteMatch([]));

        try {
            $resolver->resolve($context);
        } catch (ApiSymfonyValidatorChainException $exception) {
            self::assertCount(1, $exception->chain);
            $violation = $exception->chain[0];

            self::assertSame('name', $violation->getPropertyPath());
            self::assertStringContainsString('This value should not be null', $violation->getMessage());

            return;
        }

        self::fail('Expected ApiSymfonyValidatorChainException to be thrown for TypeError');
    }
}
