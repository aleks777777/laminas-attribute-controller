<?php

declare(strict_types=1);

namespace LaminasAttributeController\Validation;

use LaminasAttributeController\ParameterResolverInterface;
use LaminasAttributeController\ResolutionContext;

final class DefaultValueResolver implements ParameterResolverInterface
{
    public function resolve(ResolutionContext $context): mixed
    {
        return $context->parameter->isDefaultValueAvailable() ? $context->parameter->getDefaultValue() : null;
    }
}
