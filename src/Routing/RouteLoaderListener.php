<?php

declare(strict_types=1);

namespace LaminasAttributeController\Routing;

use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\Http\RouteInterface;
use Laminas\Router\Http\TreeRouteStack;

/**
 * @codeCoverageIgnore
 */
class RouteLoaderListener implements ListenerAggregateInterface
{
    /** @var array<int, callable> */
    private array $listeners = [];

    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_BOOTSTRAP, [$this, 'onBootstrap'], $priority);
    }

    public function detach(EventManagerInterface $events): void
    {
        foreach ($this->listeners as $index => $listener) {
            $events->detach($listener);
            unset($this->listeners[$index]);
        }
    }

    public function onBootstrap(MvcEvent $event): void
    {
        $serviceManager = $event->getApplication()->getServiceManager();

        /** @var RouteLoader $routeLoader */
        $routeLoader = $serviceManager->get(RouteLoader::class);

        /** @var TreeRouteStack<RouteInterface> $router */
        $router = $event->getApplication()->getServiceManager()->get('Router');
        $routes = $routeLoader->loadRoutes();

        // todo: should check if route already exists by name
        foreach ($routes as $name => $routeConfig) {
            $router->addRoute($name, $routeConfig);
        }
    }
}