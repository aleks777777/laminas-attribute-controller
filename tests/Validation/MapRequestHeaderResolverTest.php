<?php

declare(strict_types=1);

namespace Tests\Validation;

use Laminas\Http\Exception\InvalidArgumentException;
use Laminas\Http\Request;
use Laminas\Router\RouteMatch;
use LaminasAttributeController\ResolutionContext;
use LaminasAttributeController\Validation\AcceptHeader;
use LaminasAttributeController\Validation\MapRequestHeader;
use LaminasAttributeController\Validation\MapRequestHeaderResolver;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class MapRequestHeaderResolverTest extends TestCase
{
    private Request $request;
    private MapRequestHeaderResolver $resolver;
    private RouteMatch $routeMatch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request = new Request();
        $this->resolver = new MapRequestHeaderResolver($this->request);
        $this->routeMatch = new RouteMatch([]);
    }

    public function testThrowsWhenTypeIsUnsupported(): void
    {
        $controller = new class () {
            public function action(#[MapRequestHeader] int $header): void
            {
            }
        };

        $this->expectException(\LogicException::class);

        $this->resolveFromController($controller, 'action');
    }

    public function testResolvesStringHeader(): void
    {
        $this->request->getHeaders()->addHeaderLine('user-agent', 'Symfony');

        $controller = new class () {
            public function action(#[MapRequestHeader('user-agent')] string $userAgent): void
            {
            }
        };

        $result = $this->resolveFromController($controller, 'action');

        self::assertTrue($result->found);
        self::assertSame('Symfony', $result->value);
    }

    public function testResolvesArrayFromAcceptHeader(): void
    {
        $this->request->getHeaders()->addHeaderLine('accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8');

        $controller = new class () {
            public function action(#[MapRequestHeader('accept')] array $accept): void
            {
            }
        };

        $result = $this->resolveFromController($controller, 'action');

        self::assertSame([
            'text/html',
            'application/xhtml+xml',
            'application/xml',
            '*/*',
        ], $result->value);
    }

    public function testResolvesArrayFromAcceptLanguage(): void
    {
        $this->request->getHeaders()->addHeaderLine('accept-language', 'en-us,en;q=0.5');

        $controller = new class () {
            public function action(#[MapRequestHeader('accept-language')] array $languages): void
            {
            }
        };

        $result = $this->resolveFromController($controller, 'action');

        self::assertSame([
            'en_US',
            'en',
        ], $result->value);
    }

    public function testResolvesAcceptHeaderObject(): void
    {
        $this->request->getHeaders()->addHeaderLine('accept', 'text/html,application/xhtml+xml');

        $controller = new class () {
            public function action(#[MapRequestHeader('accept')] AcceptHeader $accept): void
            {
            }
        };

        $result = $this->resolveFromController($controller, 'action');

        self::assertInstanceOf(AcceptHeader::class, $result->value);
        self::assertSame('text/html,application/xhtml+xml', (string) $result->value);
    }

    public function testUsesDefaultValueWhenHeaderMissing(): void
    {
        $controller = new class () {
            public function action(#[MapRequestHeader('x-header')] string $header = 'fallback'): void
            {
            }
        };

        $result = $this->resolveFromController($controller, 'action');

        self::assertSame('fallback', $result->value);
    }

    public function testAllowsNullableParameters(): void
    {
        $controller = new class () {
            public function action(#[MapRequestHeader('x-header')] ?string $header): void
            {
            }
        };

        $result = $this->resolveFromController($controller, 'action');

        self::assertNull($result->value);
    }

    public function testThrowsWhenHeaderMissingAndNotNullable(): void
    {
        $controller = new class () {
            public function action(#[MapRequestHeader('x-header')] string $header): void
            {
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing header "x-header".');

        $this->resolveFromController($controller, 'action');
    }

    public function testReturnsEmptyArrayWhenHeaderMissingForArrayType(): void
    {
        $controller = new class () {
            public function action(#[MapRequestHeader('x-header')] array $headers): void
            {
            }
        };

        $result = $this->resolveFromController($controller, 'action');

        self::assertSame([], $result->value);
    }

    private function resolveFromController(object $controller, string $methodName)
    {
        $parameter = (new ReflectionMethod($controller, $methodName))->getParameters()[0];
        $context = new ResolutionContext($parameter, $this->routeMatch);

        return $this->resolver->resolve($context);
    }
}
