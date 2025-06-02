<?php

declare(strict_types=1);

namespace LaminasAttributeController;

interface ParameterResolverInterface
{
    public function resolve(ResolutionContext $context): mixed;
}
