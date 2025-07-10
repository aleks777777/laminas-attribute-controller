<?php

declare(strict_types=1);

namespace LaminasAttributeController\Security;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Header\ContentType;
use Laminas\Http\PhpEnvironment\Request;
use Laminas\Http\PhpEnvironment\Response;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use ReflectionMethod;

use function class_exists;
use function json_encode;

final class GuardPermissionListener extends AbstractListenerAggregate
{
    private GetPermissions $permissionFetcher;

    public function __construct(GetPermissions $permissionFetcher)
    {
        $this->permissionFetcher = $permissionFetcher;
    }

    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, [$this, 'onRoute'], 1500);
    }

    /** @phpstan-ignore-next-line */
    public function onRoute(MvcEvent $event)
    {
        /** @var Request $request */
        $request = $event->getRequest();
        $routeMatch = $event->getRouter()->match($request);

        if (! $routeMatch) {
            return;
        }

        /** @var class-string<AbstractController> $controller */
        $controller = $routeMatch->getParam('controller');
        $action = $routeMatch->getParam('action');

        if (! $controller || ! $action || ! class_exists($controller)) {
            return;
        }

        // get guard attribute from controller class
        $reflection = new ReflectionMethod($controller, $action . 'Action');
        $attribute = $reflection->getAttributes(PermissionGuard::class);
        if (! $attribute) {
            return;
        }
        /** @var PermissionGuard $attribute */
        $attribute = $attribute[0]->newInstance();

        $userPermissions = $this->permissionFetcher->getPermissions();

        if (! in_array($attribute->permission, $userPermissions, true)) {
            throw new AccessDeniedException('Access denied: does not have permission to access this resource');
        }
    }
}
