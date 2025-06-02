<?php

declare(strict_types=1);

namespace LaminasAttributeController;

use Laminas\Router\RouteMatch;
use ReflectionParameter;

class ResolutionContext
{
    public function __construct(
        public ReflectionParameter $parameter,
        public RouteMatch $routeMatch,
    ) {
    }

    /**
     * @return \ReflectionAttribute
     */
    public function getAttributes(): array
    {
        return $this->parameter->getAttributes();
    }
}
