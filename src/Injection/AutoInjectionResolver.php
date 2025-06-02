<?php

declare(strict_types=1);

namespace LaminasAttributeController\Injection;

use LaminasAttributeController\ParameterResolverInterface;
use LaminasAttributeController\ResolutionContext;
use Psr\Container\ContainerInterface;
use ReflectionNamedType;

final readonly class AutoInjectionResolver implements ParameterResolverInterface
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function resolve(ResolutionContext $context): mixed
    {
        if (! $context->parameter->getType() instanceof ReflectionNamedType) {
            return null;
        }
        if ($this->container->has($context->parameter->getType()->getName())) {
            return $this->container->get($context->parameter->getType()->getName());
        }

        return null;
    }
}
