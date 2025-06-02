<?php

declare(strict_types=1);

namespace LaminasAttributeController\Routing;

use Laminas\Router\Http\Method;
use Laminas\Router\Http\Segment;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use function array_filter;
use function array_keys;
use function class_exists;
use function str_ends_with;
use function strtolower;
use function strtoupper;
use function substr;

final readonly class RouteLoader
{
    public function __construct(
        private array $config,
    ) {
    }

    /**
     * @throws ReflectionException
     * @return array<string, array<string, mixed>>
     */
    public function loadRoutes(): array
    {
        $routes = [];
        /** @var class-string<object> $controllerClass */
        foreach ($this->getControllers() as $controllerClass) {
            $reflection = new ReflectionClass($controllerClass);

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $attributes = $method->getAttributes(Route::class);

                /* @var Route $route */
                foreach ($attributes as $attribute) {
                    $route = $attribute->newInstance();

                    $actionName = $this->normalizeActionName($method->getName());

                    $routeConfig = [
                        'type' => Segment::class,
                        'options' => [
                            'route' => $route->path,
                            'defaults' => [
                                'controller' => $controllerClass,
                                'action' => $actionName,
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [],
                    ];

                    foreach ($route->methods as $httpMethod) {
                        $routeConfig['child_routes'][strtolower($httpMethod)] = [
                            'type' => Method::class,
                            'options' => [
                                'verb' => strtoupper($httpMethod),
                            ],
                        ];
                    }

                    $routes[$route->name ?: $route->path] = $routeConfig;
                }
            }
        }

        return $routes;
    }

    private function normalizeActionName(string $methodName): string
    {
        return str_ends_with($methodName, 'Action') ? substr($methodName, 0, -6) : $methodName;
    }

    /**
     * @return string[]
     */
    private function getControllers(): array
    {
        $config = array_keys($this->config);

        // exclude alias
        return array_filter($config, static function (string $controller): bool {
            return class_exists($controller);
        });
    }
}