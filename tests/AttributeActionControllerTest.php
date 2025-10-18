<?php

declare(strict_types=1);

namespace Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use InvalidArgumentException;
use Laminas\EventManager\EventManager;
use Laminas\Http\Exception\InvalidArgumentException as LaminasInvalidArgumentException;
use Laminas\Http\Request;
use Laminas\Mvc\Application;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\Http\TreeRouteStack;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\Parameters;
use Laminas\Stdlib\ResponseInterface;
use LaminasAttributeController\ActionParameterResolver;
use LaminasAttributeController\AttributeActionController;
use LaminasAttributeController\Injection\AutoInjectionResolver;
use LaminasAttributeController\Injection\AutowireResolver;
use LaminasAttributeController\Routing\FromRouteResolver;
use LaminasAttributeController\Routing\Route;
use LaminasAttributeController\Routing\RouteLoader;
use LaminasAttributeController\Security\CurrentUser;
use LaminasAttributeController\Security\CurrentUserValueResolver;
use LaminasAttributeController\Security\GetCurrentUser;
use LaminasAttributeController\Validation\DefaultValueResolver;
use LaminasAttributeController\Validation\QueryParam;
use LaminasAttributeController\Validation\QueryParamResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use function get_class;

class AttributeActionControllerTest extends TestCase
{
    private ServiceManager $serviceManager;

    /**
     * @dataProvider dataProviderForDispatch
     * @test
     */
    public function dispatch_with_request(
        AttributeActionController $controller,
        Request                   $request,
        ?array                    $expected = null,
        ?string                   $exception = null,
        ?string                   $exceptionMessage = null
    ): void {
        $this->serviceManager = new ServiceManager();
        $this->serviceManager->setService(get_class($controller), $controller);

        $entityManager = $this->createEntityManager();
        $this->serviceManager->setService(EntityManagerInterface::class, $entityManager);

        $currentUserProvider = new TestGetCurrentUser();
        $currentUserProvider->auth(new User(1, 'test-auth@client.com'));
        $this->serviceManager->setService(GetCurrentUser::class, $currentUserProvider);

        $logger = new PromptedMonologRegistry();
        $this->serviceManager->setService(PromptedMonologRegistryInterface::class, $logger);
        $this->serviceManager->setService(PromptedMonologRegistry::class, $logger);


        $resolver = new ActionParameterResolver(
            new FromRouteResolver($entityManager),
            new QueryParamResolver($request),
            new AutowireResolver($this->serviceManager),
            new AutoInjectionResolver($this->serviceManager),
            new CurrentUserValueResolver($currentUserProvider),
            new DefaultValueResolver(),
        );
        $this->serviceManager->setService(ActionParameterResolver::class, $resolver);

        $router = new TreeRouteStack();
        $routeLoader = new RouteLoader([get_class($controller) => get_class($controller)]);

        foreach ($routeLoader->loadRoutes() as $name => $config) {
            $router->addRoute($name, $config);
        }

        $this->serviceManager->setService('Router', $router);

        $routeMatch = $router->match($request);

        if ($exception) {
            $this->expectException($exception);
        }

        if ($exceptionMessage) {
            $this->expectExceptionMessage($exceptionMessage);
        }

        $result = $controller->onDispatch($this->createMvcEvent(
            $router,
            $request,
            $routeMatch
        ));

        if ($expected) {
            $this->assertEquals($expected, $result);
        }
    }

    public function dataProviderForDispatch(): array
    {
        return [
            'case without parameters' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/user', methods: ['GET'], name: 'user_index')]
                    public function indexAction(): array
                    {
                        return ['status' => 'User created'];
                    }
                },
                'request' => $this->createHttpRequest('/user', 'GET'),
                'expected' => ['status' => 'User created'],
            ],
            'case with query param' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/user/filter', methods: ['GET'], name: 'user_filter')]
                    public function filterUserAction(#[QueryParam('filter')] ?string $filter = null): array
                    {
                        return ['filter' => $filter];
                    }
                },
                'request' => $this->createHttpRequest('/user/filter', 'GET', ['filter' => 'active']),
                'expected' => ['filter' => 'active'],
            ],
            'case with required query param' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/user/filter', methods: ['GET'], name: 'user_filter')]
                    public function filterUserAction(#[QueryParam('filter', required: true)] string $filter): array
                    {
                        return ['filter' => $filter];
                    }
                },
                'request' => $this->createHttpRequest('/user/filter', 'GET'),
                'expected' => null,
                'exception' => InvalidArgumentException::class,
                'exceptionMessage' => "Query parameter 'filter' is required",
            ],
            'case with incorrect route (404)' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/user', methods: ['GET'], name: 'user')]
                    public function filterUserAction(): array
                    {
                        return [];
                    }
                },
                'request' => $this->createHttpRequest('/user/filter', 'GET', ['filter' => 'active']),
                'expected' => null,
                'exception' => InvalidArgumentException::class,
                'exceptionMessage' => 'RouteMatch not found',
            ],
            'case with injection' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/logger', methods: ['GET'], name: 'logger')]
                    public function logAction(PromptedMonologRegistryInterface $logger): array
                    {
                        return [$logger::class];
                    }
                },
                'request' => $this->createHttpRequest('/logger', 'GET'),
                'expected' => [PromptedMonologRegistry::class],
            ],
            'current user' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/route', methods: ['GET'], name: 'route')]
                    public function findAction(#[CurrentUser] User $client): array
                    {
                        return [$client->getEmail()];
                    }
                },
                'request' => $this->createHttpRequest('/route', 'GET'),
                'expected' => ['test-auth@client.com'],
            ],
            'current user without type' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/route', methods: ['GET'], name: 'route')]
                    public function findAction(PromptedMonologRegistryInterface $inject, #[CurrentUser] $client): array
                    {
                        return [$client->getEmail()];
                    }
                },
                'request' => $this->createHttpRequest('/route', 'GET'),
                'expected' => null,
                'exception' => InvalidArgumentException::class,
                'exceptionMessage' => 'For mapping `current user` type is required',
            ],
        ];
    }

    private function createEntityManager(): EntityManagerInterface
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $entityManager->method('getRepository')->willReturnCallback(function (string $class) {
            return new class($class) implements ObjectRepository {
                public function __construct(private readonly string $class)
                {
                }

                public function find($id): ?object
                {
                    if ($this->class === Entity::class && is_numeric($id)) {
                        $intId = (int)$id;

                        return $intId === 5 ? new Entity($intId) : null;
                    }

                    return null;
                }

                public function findAll(): array
                {
                    return [];
                }

                public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array
                {
                    return [];
                }

                public function findOneBy(array $criteria): ?object
                {
                    return null;
                }

                public function getClassName(): string
                {
                    return $this->class;
                }
            };
        });

        $entityManager->method('getClassMetadata')->willReturnCallback(function (string $class) {
            if ($class === Entity::class) {
                return (object)['isMappedSuperclass' => false];
            }

            throw new RuntimeException('metadata not found');
        });

        return $entityManager;
    }

    private function createMvcEvent(TreeRouteStack $router, Request $request, $routeMatch = null): MvcEvent
    {
        $event = new MvcEvent();
        $event->setRouter($router);
        if ($routeMatch) {
            $event->setRouteMatch($routeMatch);
        }

        $application = new Application(
            $this->serviceManager,
            $this->createMock(EventManager::class),
            $request,
            $this->createMock(ResponseInterface::class)
        );
        $event->setApplication($application);
        $event->setRequest($request);

        return $event;
    }

    private function createHttpRequest(string $path, string $method, array $queryParams = [], array $postData = [], ?string $json = null): Request
    {
        $request = new Request();
        $request->setUri($path);
        $request->setMethod($method);

        if (!empty($queryParams)) {
            $request->getQuery()->fromArray($queryParams);
        }
        if (!empty($postData)) {
            $request->setPost(new Parameters($postData));
        }

        if ($json) {
            $request->setContent($json);
        }
        $request->getHeaders()->addHeaderLine('Content-Type', 'application/json');

        return $request;
    }
}


interface PromptedMonologRegistryInterface
{
}

final class PromptedMonologRegistry implements PromptedMonologRegistryInterface
{
}

final readonly class User
{
    public function __construct(private int $id, private string $email)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
    public function getEmail(): string
    {
        return $this->email;
    }
}
final readonly class Entity
{
    public function __construct(private int $id)
    {
    }

    public function getId(): int
    {
        return $this->id;
    }
}

final class TestGetCurrentUser implements GetCurrentUser
{
    private ?User $user = null;

    public function auth(User $user): void
    {
        $this->user = $user;
    }

    public function getCurrentUser(): ?User
    {
        return $this->user;
    }
}
