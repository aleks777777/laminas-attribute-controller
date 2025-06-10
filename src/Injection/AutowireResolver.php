<?php

declare(strict_types=1);

namespace LaminasAttributeController\Injection;

use LaminasAttributeController\ParameterResolverInterface;
use LaminasAttributeController\ParameterValue;
use LaminasAttributeController\ResolutionContext;
use Psr\Container\ContainerInterface;
use ReflectionNamedType;

final readonly class AutowireResolver implements ParameterResolverInterface
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function resolve(ResolutionContext $context): ParameterValue
    {
        foreach ($context->getAttributes() as $attribute) {
            $instance = $attribute->newInstance();

            if ($instance instanceof Autowire) {
                $type = $context->parameter->getType();
                $alias = $instance->alias ?? ($type instanceof ReflectionNamedType ? $type->getName() : null);

                if (! $alias) {
                    return ParameterValue::notFound();
                }

                if ($this->container->has($alias)) {
                    return ParameterValue::found(Autowire::class, $this->container->get($alias));
                }

                return ParameterValue::notFound();
            }
        }

        return ParameterValue::notFound();
    }
}
