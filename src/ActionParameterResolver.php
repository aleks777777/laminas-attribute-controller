<?php

declare(strict_types=1);

namespace LaminasAttributeController;

use Laminas\Http\Exception\InvalidArgumentException;
use Laminas\Router\RouteMatch;
use ReflectionException;
use ReflectionMethod;

final readonly class ActionParameterResolver
{
    /** @var ParameterResolverInterface[] */
    private array $resolvers;

    public function __construct(
        ParameterResolverInterface ...$resolvers
    ) {
        $this->resolvers = $resolvers;
    }

    /**
     * @param  object              $controller
     * @param  string              $method
     * @param  RouteMatch          $routeMatch
     * @throws ReflectionException
     * @return array<mixed>
     */
    public function resolveMethodParameters(object $controller, string $method, RouteMatch $routeMatch): array
    {
        $resolvedParams = [];
        $reflection = new ReflectionMethod($controller, $method);
        $parameters = $reflection->getParameters();

        foreach ($parameters as $param) {
            $context = new ResolutionContext($param, $routeMatch);

            $resolvedParams[] = $this->resolveParameter($context);
        }

        return $resolvedParams;
    }

    private function resolveParameter(ResolutionContext $context): mixed
    {
        foreach ($this->resolvers as $resolver) {
            $result = $resolver->resolve($context);

            if ($result->found) {
                return $result->value;
            }
        }

        throw new InvalidArgumentException("Unable to resolve parameter '{$context->parameter->getName()}'");
    }
}
