<?php

declare(strict_types=1);

namespace LaminasAttributeController;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class ActionParameterResolverFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ActionParameterResolver
    {
        $resolversClasses = $container->get('config')['laminas-attribute-controller']['resolvers'] ?? [];
        $resolvers = [];
        foreach ($resolversClasses as $resolverClass) {
            if (!is_subclass_of($resolverClass, ParameterResolverInterface::class)) {
                throw new \InvalidArgumentException(sprintf(
                    'Resolver class "%s" must implement %s',
                    $resolverClass,
                    ParameterResolverInterface::class
                ));
            }
            $resolvers[] = $container->get($resolverClass);
        }

        return new ActionParameterResolver(
            ...$resolvers
        );
    }
}
