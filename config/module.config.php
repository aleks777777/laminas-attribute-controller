<?php

declare(strict_types=1);

use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializerInterface;
use Laminas\ServiceManager\Factory\InvokableFactory;
use LaminasAttributeController\ActionParameterResolver;
use LaminasAttributeController\ActionParameterResolverFactory;
use LaminasAttributeController\Injection\AutoInjectionResolver;
use LaminasAttributeController\Injection\AutowireResolver;
use LaminasAttributeController\Routing\FromRouteResolver;
use LaminasAttributeController\Routing\RouteLoader;
use LaminasAttributeController\Routing\RouteLoaderListener;
use LaminasAttributeController\Security\CurrentUserValueResolver;
use LaminasAttributeController\Security\GetCurrentUser;
use LaminasAttributeController\Security\GuardListener;
use LaminasAttributeController\Security\NullCurrentUser;
use LaminasAttributeController\Validation\DefaultValueResolver;
use LaminasAttributeController\Validation\MapQueryStringResolver;
use LaminasAttributeController\Validation\MapRequestHeaderResolver;
use LaminasAttributeController\Validation\MapRequestPayloadResolver;
use LaminasAttributeController\Validation\QueryParamResolver;
use Psr\Container\ContainerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

return [
    'laminas-attribute-controller' => [
        'resolvers' => [
            FromRouteResolver::class,
            MapRequestPayloadResolver::class,
            MapQueryStringResolver::class,
            QueryParamResolver::class,
            AutowireResolver::class,
            AutoInjectionResolver::class,
            CurrentUserValueResolver::class,
            MapRequestHeaderResolver::class,
            DefaultValueResolver::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            FromRouteResolver::class => function (ContainerInterface $container) {
                return new FromRouteResolver($container->get(EntityManagerInterface::class));
            },
            MapRequestHeaderResolver::class => function (ContainerInterface $container) {
                $request = $container->get('request');

                return new MapRequestHeaderResolver($request);
            },
            MapRequestPayloadResolver::class => function (ContainerInterface $container) {

                $serializer = $container->get(SerializerInterface::class);
                $validator = $container->get(ValidatorInterface::class);
                $request = $container->get('request');

                return new MapRequestPayloadResolver($serializer, $validator, $request);
            },
            MapQueryStringResolver::class => function (ContainerInterface $container) {

                $serializer = $container->get(SerializerInterface::class);
                $validator = $container->get(ValidatorInterface::class);
                $request = $container->get('request');

                return new MapQueryStringResolver($serializer, $validator, $request);
            },
            QueryParamResolver::class => function (ContainerInterface $container) {
                $request = $container->get('request');

                return new QueryParamResolver($request);
            },
            AutowireResolver::class => function (ContainerInterface $container) {
                return new AutowireResolver($container);
            },
            AutoInjectionResolver::class => function (ContainerInterface $container) {
                return new AutoInjectionResolver($container);
            },
            CurrentUserValueResolver::class => function (ContainerInterface $container) {
                if (!$container->has(GetCurrentUser::class)) {
                    throw new RuntimeException('GetCurrentUser service is not registered in the container.');
                }
                $getCurrentUser = $container->get(GetCurrentUser::class);

                return new CurrentUserValueResolver($getCurrentUser);
            },
            ActionParameterResolver::class => ActionParameterResolverFactory::class,
            RouteLoader::class => function (ContainerInterface $container) {
                $factories = $container->get('config')['controllers']['factories'] ?? [];
                $invokables = $container->get('config')['controllers']['invokables'] ?? [];

                return new RouteLoader(
                    array_merge(
                        $factories,
                        $invokables,
                        []
                    )
                );
            },
            GuardListener::class => function (ContainerInterface $container) {
                return new GuardListener(
                    $container->get(GetCurrentUser::class)
                );
            },
            RouteLoaderListener::class => InvokableFactory::class,
        ],
        'invokables' => [
            NullCurrentUser::class => NullCurrentUser::class,
            DefaultValueResolver::class => DefaultValueResolver::class,
        ],
        'aliases' => [
            GetCurrentUser::class => NullCurrentUser::class,
        ],
    ],
    'listeners' => [
        RouteLoaderListener::class,
        GuardListener::class,
    ],
];
