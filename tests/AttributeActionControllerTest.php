<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Controller;

use Laminas\Router\Http\TreeRouteStack;
use LaminasAttributeController\Injection\Autowire;
use LaminasAttributeController\Validation\QueryParam;
use LaminasAttributeController\Routing\Route;
use LaminasAttributeController\AttributeActionController;
use LaminasAttributeController\ActionParameterResolver;
use LaminasAttributeController\Injection\AutoInjectionResolver;
use LaminasAttributeController\Injection\AutowireResolver;
use LaminasAttributeController\Validation\QueryParamResolver;
use Laminas\EventManager\EventManager;
use Laminas\Http\Exception\InvalidArgumentException;
use Laminas\Http\Request;
use Laminas\Mvc\Application;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\Parameters;
use Laminas\Stdlib\ResponseInterface;
use PHPUnit\Framework\TestCase;
use function get_class;
use function json_encode;

class AttributeActionControllerTest extends TestCase
{
    private ServiceManager $serviceManager;

    /**
     * @dataProvider dataProviderForDispatch
     *
     * @test
     * @group new
     * @param mixed      $request
     * @param mixed      $expected
     * @param mixed|null $exception
     * @param mixed|null $exceptionMessage
     */
    public function dispatch_with_request(AttributeActionController $controller, Request $request, ?array $expected = null, ?string $exception = null, string $exceptionMessage = null): void
    {
        $this->serviceManager = new ServiceManager();
        $this->serviceManager->setService(get_class($controller), $controller);

//        $entityManager = $this->createMock(EntityManagerInterface::class);

//        $validator = (new ValidatorBuilder())->enableAnnotationMapping()->getValidator();
//        $this->serviceManager->setService(EntityManagerInterface::class, $entityManager);
//        $clientAuth = $this->createMock(GetAuthenticatedClientServiceInterface::class);
//        $clientAuth->method('execute')->willReturn((new Client())->setEmail('test-auth@client.com'));
        $resolver = new ActionParameterResolver(
//            new FromRouteResolver($entityManager),
//            new MapRequestPayloadResolver((new SerializerBuilder())->build(), $validator, $request),
            new QueryParamResolver($request),
            new AutowireResolver($this->serviceManager),
            new AutoInjectionResolver($this->serviceManager),
//            new CurrentUserValueResolver($clientAuth),
            new DefaultValueResolver(),
        );
        $this->serviceManager->setService(ActionParameterResolver::class, $resolver);

//        $dot = $this->createMock(DotConfigurationServiceInterface::class);
//        $dot->method('get')->willReturnMap([
//            ['controllers.factories', [], [get_class($controller) => get_class($controller)]],
//            ['controllers.invokables', [], []],
//        ]);

//        $this->serviceManager->setService(DotConfigurationServiceInterface::class, $dot);

//        $routeLoader = new RouteLoader($dot);
//        $this->serviceManager->setService(RouteLoader::class, $routeLoader);

//        $listener = new RouteLoaderListener();

        $router = new TreeRouteStack();

//        $translator = $this->createMock(Translator::class);
//        $translator->method('translate')->will($this->returnArgument(0));
//        $this->serviceManager->setService('translator', $translator);
//        $router->setTranslator($translator);
//        $router->setTranslatorEnabled(false); // Enable the translator

        $this->serviceManager->setService('Router', $router);
//        $listener->onBootstrap($this->createMvcEvent($router, $request));

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
                    #[Route(path: '/user', methods: ['POST'], name: 'user_create')]
                    public function createUserAction(): array
                    {
                        return ['status' => 'User created'];
                    }
                },
                'request' => $this->createHttpRequest('/user', 'POST'),
                'expected' => ['status' => 'User created'],
                'exception' => null,
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
                'exception' => null,
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
            'case query param with validation' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/user/filter', methods: ['GET'], name: 'user_filter')]
                    public function filterUserAction(#[QueryParam('filter', [new Range(['min' => 4])])] string $filter): array
                    {
                        return ['filter' => $filter];
                    }
                },
                'request' => $this->createHttpRequest('/user/filter', 'GET', ['filter' => 3]),
                'expected' => null,
                'exception' => InvalidArgumentException::class,
                'exceptionMessage' => "Validation failed for 'filter': This value should be 4 or more.",
            ],
            'case with incorrect route (404)' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/user', methods: ['GET'], name: 'user_filter')]
                    public function filterUserAction(): array
                    {
                        return [];
                    }
                },
                'request' => $this->createHttpRequest('/user/filter', 'GET', ['filter' => 'active']),
                'expected' => null,
                'exception' => InvalidArgumentException::class,
            ],
            'case with injection' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/user', methods: ['GET'], name: 'user')]
                    public function filterUserAction(PromptedMonologRegistryInterface $logger): array
                    {
                        return [$logger::class];
                    }
                },
                'request' => $this->createHttpRequest('/user', 'GET'),
                'expected' => [PromptedMonologRegistry::class],
            ],
            'case with mapping input and validation' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/user', methods: ['POST'], name: 'user')]
                    public function createUserAction(#[MapRequestPayload] ExampleInput $input): array
                    {
                        return [$input->title];
                    }
                },
                'request' => $this->createHttpRequest('/user', 'POST', [], [], json_encode(['title' => 'test'])),
                'expected' => ['test'],
            ],
            'case with mapping input and failed on validation' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/user', methods: ['POST'], name: 'user')]
                    public function createUserAction(#[MapRequestPayload] ExampleInput $input): array
                    {
                        return [$input->title];
                    }
                },
                'request' => $this->createHttpRequest('/user', 'POST', [], [], json_encode(['title' => 'te', 'testField' => '', 'range' => 1])),
                'expected' => ['test'],
                'exception' => ApiSymfonyValidatorChainException::class,
                'exceptionMessage' => 'Input validation failed',
            ],
            'case with mapping input without type' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/user', methods: ['POST'], name: 'user')]
                    public function createUserAction(#[MapRequestPayload] $input): array
                    {
                        return [$input->title];
                    }
                },
                'request' => $this->createHttpRequest('/user', 'POST', [], [], json_encode(['title' => 'test'])),
                'expected' => ['test'],
                'exception' => InvalidArgumentException::class,
                'exceptionMessage' => 'For mapping payload type is required',
            ],
            'case with payload without mapping' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/user', methods: ['POST'], name: 'user')]
                    public function createUserAction(ExampleInput $input): array
                    {
                        return [];
                    }
                },
                'request' => $this->createHttpRequest('/user', 'POST', [], ['title' => 'test'], json_encode(['title' => 'test'])),
                'expected' => ['test'],
                'exception' => InvalidArgumentException::class,
                'exceptionMessage' => "Unable to resolve parameter 'input'",
            ],
            'case try inject without type' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/user', methods: ['GET'], name: 'user')]
                    public function filterUserAction($logger): array
                    {
                        return [$logger::class];
                    }
                },
                'request' => $this->createHttpRequest('/user', 'GET'),
                'expected' => null,
                'exception' => InvalidArgumentException::class,
                'exceptionMessage' => "Unable to resolve parameter 'logger'",
            ],
            'case with autowire' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/user', methods: ['GET'], name: 'user')]
                    public function filterUserAction(#[Autowire('logger')] $logger): array
                    {
                        return [$logger::class];
                    }
                },
                'request' => $this->createHttpRequest('/user', 'GET'),
                'expected' => [PromptedMonologRegistry::class],
            ],
            'case with autowire without alias and type' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/user', methods: ['GET'], name: 'user')]
                    public function filterUserAction(#[Autowire] $logger): array
                    {
                        return [$logger::class];
                    }
                },
                'request' => $this->createHttpRequest('/user', 'GET'),
                'expected' => null,
                'exception' => InvalidArgumentException::class,
                'exceptionMessage' => "Unable to resolve parameter 'logger'",
            ],
            'case with scalar route param' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/user/:id', methods: ['GET'], name: 'user')]
                    public function findAction(int $id): array
                    {
                        return [$id];
                    }
                },
                'request' => $this->createHttpRequest('/user/5', 'GET'),
                'expected' => [5],
            ],
            'case with entity route param' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/user/:id', methods: ['GET'], name: 'user')]
                    public function findAction(User $user): array
                    {
                        return [$user->getId()];
                    }
                },
                'request' => $this->createHttpRequest('/user/5', 'GET'),
                'expected' => [5],
            ],
            'case with entity route param but throw mapping exception' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/user/:id', methods: ['GET'], name: 'user')]
                    public function findAction(Client $exceptionMapping): array
                    {
                        // by setup test resolved should throw mapping exception if entity Client
                        return [$exceptionMapping];
                    }
                },
                'request' => $this->createHttpRequest('/user/5', 'GET'),
                'expected' => null,
                'exception' => InvalidArgumentException::class,
                'exceptionMessage' => "Unable to resolve parameter 'exceptionMapping'",
            ],
            'case with not found entity' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/user/:id', methods: ['GET'], name: 'user')]
                    public function findAction(User $user): array
                    {
                        return [$user->getId()];
                    }
                },
                'request' => $this->createHttpRequest('/user/10', 'GET'),
                'expected' => null,
                'exception' => InvalidArgumentException::class,
            ],
            'case with entity without params' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/user', methods: ['GET'], name: 'user')]
                    public function findAction(User $user): array
                    {
                        return [$user->getId()];
                    }
                },
                'request' => $this->createHttpRequest('/user', 'GET'),
                'expected' => null,
                'exception' => InvalidArgumentException::class,
                'exceptionMessage' => "Unable to resolve parameter 'user'",
            ],
            'case with entity and injection' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/user/:id', methods: ['GET'], name: 'user')]
                    public function findAction(User $user, PromptedMonologRegistryInterface $inject): array
                    {
                        return [$user->getId(), $inject::class];
                    }
                },
                'request' => $this->createHttpRequest('/user/5', 'GET'),
                'expected' => [5, PromptedMonologRegistry::class],
            ],
            'current user' => [
                'controller' => new class extends AttributeActionController {
                    #[Route(path: '/route', methods: ['GET'], name: 'route')]
                    public function findAction(#[CurrentUser] Client $client): array
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

    private function createHttpRequest(string $path, string $method, array $queryParams = [], array $postData = [], string $json = null): Request
    {
        $request = new Request();
        $request->setUri($path);
        $request->setMethod($method);

        if (! empty($queryParams)) {
            $request->getQuery()->fromArray($queryParams);
        }
        if (! empty($postData)) {
            $request->setPost(new Parameters($postData));
        }

        if ($json) {
            $request->setContent($json);
        }
        $request->getHeaders()->addHeaderLine('Content-Type', 'application/json');

        return $request;
    }
}
