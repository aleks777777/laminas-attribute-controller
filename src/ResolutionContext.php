<?php

declare(strict_types=1);

namespace LaminasAttributeController;

use Laminas\Router\RouteMatch;
use ReflectionAttribute;
use ReflectionParameter;

class ResolutionContext
{
    public function __construct(
        public ReflectionParameter $parameter,
        public RouteMatch $routeMatch,
    ) {
    }

    /**
     * @return array<ReflectionAttribute>
     */
    public function getAttributes(): array
    {
        return $this->parameter->getAttributes();
    }
}
