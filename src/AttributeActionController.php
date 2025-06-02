<?php

declare(strict_types=1);

namespace LaminasAttributeController;

use Assert\Assertion;
use InvalidArgumentException;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;

abstract class AttributeActionController extends AbstractController
{
    public function onDispatch(MvcEvent $e): mixed
    {
        return $this->handleAction($e);
    }

    private function handleAction(MvcEvent $e): mixed
    {
        $routeMatch = $e->getRouteMatch();
        if (! $routeMatch) {
            throw new InvalidArgumentException('RouteMatch not found');
        }

        $action = $routeMatch->getParam('action', 'not-found');
        Assertion::string($action, 'Action must be a string');

        $method = static::getMethodFromAction($action);

        if (!$method || ! method_exists($this, $method)) {
            throw new InvalidArgumentException("Action method '$method' not found");
        }

        /** @var ActionParameterResolver $parameterResolver */
        $parameterResolver = $e->getApplication()->getServiceManager()->get(ActionParameterResolver::class);

        $parameters = $parameterResolver->resolveMethodParameters($this, $method, $routeMatch);

        /** @phpstan-ignore-next-line */
        $actionResponse = call_user_func_array([$this, $method], $parameters);

        $e->setResult($actionResponse);

        return $actionResponse;
    }
}
