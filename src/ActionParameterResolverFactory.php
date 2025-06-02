<?php

declare(strict_types=1);

namespace LaminasAttributeController;

use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LaminasAttributeController\Injection\AutoInjectionResolver;
use LaminasAttributeController\Injection\AutowireResolver;
use LaminasAttributeController\Routing\FromRouteResolver;
use LaminasAttributeController\Security\CurrentUserValueResolver;
use LaminasAttributeController\Security\GetCurrentUser;
use LaminasAttributeController\Validation\DefaultValueResolver;
use LaminasAttributeController\Validation\MapRequestPayloadResolver;
use LaminasAttributeController\Validation\QueryParamResolver;
use Psr\Container\ContainerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ActionParameterResolverFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): ActionParameterResolver
    {
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var GetCurrentUser $user */
        $user = $container->get(GetCurrentUser::class);

        $serializer = $container->get(SerializerInterface::class);
        $validator = $container->get(ValidatorInterface::class);
        $request = $container->get('request');

        return new ActionParameterResolver(
            new FromRouteResolver($em),
            new MapRequestPayloadResolver($serializer, $validator, $request),
            new QueryParamResolver($request),
            new AutowireResolver($container),
            new AutoInjectionResolver($container),
            new CurrentUserValueResolver($user),
            new DefaultValueResolver()
        );
    }
}
