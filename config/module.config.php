<?php

declare(strict_types=1);

use Common\Factory\DotConfigurationServiceInterface;
use Laminas\ServiceManager\Factory\InvokableFactory;
use LaminasAttributeController\ActionParameterResolver;
use LaminasAttributeController\ActionParameterResolverFactory;
use LaminasAttributeController\Routing\RouteLoader;
use LaminasAttributeController\Routing\RouteLoaderListener;
use LaminasAttributeController\Security\GetCurrentUser;
use LaminasAttributeController\Security\NullCurrentUser;
use Psr\Container\ContainerInterface;

return [
    'service_manager' => [
        'factories' => [
            ActionParameterResolver::class => ActionParameterResolverFactory::class,
            RouteLoader::class => function (ContainerInterface $container) {
                return new RouteLoader(
                    array_merge(
                        $container->get(DotConfigurationServiceInterface::class)->get('controllers.factories', []),
                        $container->get(DotConfigurationServiceInterface::class)->get('controllers.invokables', []),
                        []
                    )
                );
            },
            RouteLoaderListener::class => InvokableFactory::class,
        ],
        'invokables' => [
            NullCurrentUser::class => NullCurrentUser::class,
        ],
        'aliases' => [
            GetCurrentUser::class => NullCurrentUser::class,
        ],
    ],
    'listeners' => [
        RouteLoaderListener::class,
    ],
];
