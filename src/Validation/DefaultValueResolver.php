<?php

declare(strict_types=1);

namespace LaminasAttributeController\Validation;

use LaminasAttributeController\ParameterResolverInterface;
use LaminasAttributeController\ParameterValue;
use LaminasAttributeController\ResolutionContext;

final class DefaultValueResolver implements ParameterResolverInterface
{
    public function resolve(ResolutionContext $context): ParameterValue
    {
        if ($context->parameter->isDefaultValueAvailable()) {
            return ParameterValue::found(null, $context->parameter->getDefaultValue());
        }

        return ParameterValue::notFound();
    }
}
