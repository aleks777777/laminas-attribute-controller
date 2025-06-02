<?php

declare(strict_types=1);

namespace LaminasAttributeController\Security;

use Laminas\Http\Exception\InvalidArgumentException;
use LaminasAttributeController\ParameterResolverInterface;
use LaminasAttributeController\ResolutionContext;
use ReflectionNamedType;

final readonly class CurrentUserValueResolver implements ParameterResolverInterface
{
    public function __construct(
        private GetCurrentUser $currentUser,
    ) {
    }

    public function resolve(ResolutionContext $context): mixed
    {
        foreach ($context->getAttributes() as $attribute) {
            if ($attribute->getName() !== CurrentUser::class) {
                continue;
            }

            if (! $context->parameter->getType() instanceof ReflectionNamedType) {
                throw new InvalidArgumentException('For mapping `current user` type is required');
            }

            return $this->currentUser->getCurrentUser();
        }

        return null;
    }
}
