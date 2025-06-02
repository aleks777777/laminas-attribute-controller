# Laminas Attribute Controller

## Description
This package provides attribute-based controllers for use in Laminas applications. It simplifies route management, dependency injection, and input handling in controller arguments.

## Installation

### Requirements
- PHP >= 8.0
- Laminas MVC >= 3.0
- Composer

### Instructions
1. Install the package:
   ```
   composer require a7-tech/laminas-attribute-controller
   ```
2. Set up the module in your Laminas application by adding it to the `application.config.php`:
   ```php
   return [
       'modules' => [
           // other modules...
           LaminasAttributeController\Module::class,
       ],
   ];
   ```

## Usage

### Example Controller with Attributes
```php
use LaminasAttributeController\AttributeActionController;
use LaminasAttributeController\Routing\Route;

#[Route('/user', name: 'user')]
final class UserController extends AttributeActionController
{
    #[Route('/list', name: 'user_list', methods: ['GET'])]
    public function listAction()
    {
        return ['users' => []];
    }

    #[Route('/add', name: 'user_add', methods: ['POST'])]
    public function addAction()
    {
        return ['status' => 'success'];
    }
}
```

### Route Registration
Routes are automatically registered through attributes when the module is enabled.

## Attributes Documentation

### Route
The `Route` attribute defines routing for controller methods.

```php
use LaminasAttributeController\Routing\Route;

#[Route('/api/users', name: 'users_route', methods: ['GET', 'POST'])]
public function usersAction()
{
    // Method implementation
}
```

**Parameters:**
- `path` (string): The URL path for the route
- `name` (string|null): Optional name for the route
- `methods` (array<string>): HTTP methods allowed for this route, defaults to ['GET']

### QueryParam
The `QueryParam` attribute extracts and validates query parameters from the request.

```php
use LaminasAttributeController\Validation\QueryParam;
use Symfony\Component\Validator\Constraints as Assert;

public function searchAction(
    #[QueryParam('query', constraints: [new Assert\NotBlank(), new Assert\Length(min: 3)], required: true)] string $searchQuery
) {
    // Use $searchQuery which has been extracted from the request query parameters
    return ['results' => $this->searchService->search($searchQuery)];
}
```

**Parameters:**
- `name` (string): The name of the query parameter
- `constraints` (array): Array of Symfony validator constraints to apply
- `required` (bool): Whether the parameter is required, defaults to false

### Autowire
The `Autowire` attribute enables automatic dependency injection.

```php
use LaminasAttributeController\Injection\Autowire;
use App\Service\UserService;

public function listAction(
    #[Autowire] UserService $userService,
    #[Autowire('custom.service.alias')] $customService
) {
    $users = $userService->getAllUsers();
    // Method implementation
}
```

**Parameters:**
- `alias` (string|null): Optional service alias for the container, defaults to null (uses the type hint)

### IsGranted
The `IsGranted` attribute provides auth-based access control for controller methods.

```php
use LaminasAttributeController\Security\IsGranted;

#[IsGranted('FULLY_AUTHENTICATED')]
public function authorizedAction()
{
    // Only accessible to authenticated users
    return ['message' => 'authorized area'];
}
```

**Parameters:**
- `role` (string): The role required to access the method

### MapRequestPayload
The `MapRequestPayload` attribute maps the request body to a parameter.

```php
use LaminasAttributeController\Validation\MapRequestPayload;
use App\DTO\UserCreateRequest;

public function createAction(
    #[MapRequestPayload] UserCreateRequest $request
) {
    // $request is populated from the request body
    return ['id' => $this->userService->createUser($request)];
}
```

This attribute automatically deserializes the request body into the specified type.

#### Example with Multiple Fields and Validators

```php
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;

class UserCreateRequest
{
    #[Assert\NotBlank(message: "Username is required")]
    #[Assert\Length(min: 3, max: 50, minMessage: "Username must be at least {{ limit }} characters", maxMessage: "Username cannot be longer than {{ limit }} characters")]
    public string $username;

    #[Assert\NotBlank(message: "Email is required")]
    #[Assert\Email(message: "The email '{{ value }}' is not a valid email")]
    public string $email;

    #[Assert\NotBlank(message: "Password is required")]
    #[Assert\Length(min: 8, minMessage: "Password must be at least {{ limit }} characters")]
    #[Assert\Regex(
        pattern: "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/",
        message: "Password must include at least one uppercase letter, one lowercase letter, and one number"
    )]
    public string $password;

    #[Assert\NotBlank(message: "Age is required")]
    #[Assert\Range(
        min: 18,
        max: 120,
        notInRangeMessage: "You must be between {{ min }} and {{ max }} years old"
    )]
    public int $age;
}
```

Using this DTO with the `MapRequestPayload` attribute will automatically validate all fields according to the specified constraints.

### MapQueryString
The `MapQueryString` attribute maps the query string parameters to a typed object.

```php
use LaminasAttributeController\Validation\MapQueryString;
use App\DTO\UserSearchRequest;

public function searchAction(
    #[MapQueryString] UserSearchRequest $searchCriteria
) {
    // $searchCriteria is populated from the query string parameters
    return ['results' => $this->userService->search($searchCriteria)];
}
```

This attribute automatically deserializes the query string parameters into the specified type, making it easy to work with structured query parameters in GET requests.

#### Example with Multiple Fields and Validators

```php
use Symfony\Component\Validator\Constraints as Assert;
use JMS\Serializer\Annotation as Serializer;

class UserSearchRequest
{
    #[Assert\Length(min: 2, max: 50, minMessage: "Search term must be at least {{ limit }} characters")]
    public ?string $searchTerm = null;

    #[Assert\Range(
        min: 1,
        max: 100,
        notInRangeMessage: "Results per page must be between {{ min }} and {{ max }}"
    )]
    public int $limit = 20;

    #[Assert\PositiveOrZero(message: "Page number cannot be negative")]
    public int $page = 0;

    #[Assert\Choice(choices: ["asc", "desc"], message: "Sort direction must be either 'asc' or 'desc'")]
    public string $sortDirection = "asc";
}
```

Using this DTO with the `MapQueryString` attribute will automatically validate all query parameters according to the specified constraints. This example would handle a query like `?searchTerm=john&limit=50&page=2&sortDirection=desc`.

### CurrentUser
The `CurrentUser` attribute injects the current authenticated user into a parameter.
For implementation, you need to have a user entity and a service that retrieves the current user from the session or security context implemented `LaminasAttributeController\Security\GetCurrentUser`
```php
use LaminasAttributeController\Security\CurrentUser;
use App\Entity\User;

public function profileAction(
    #[CurrentUser] User $user
) {
    // $user contains the currently authenticated user
    return ['user' => $user];
}
```

This attribute provides easy access to the authenticated user without manual retrieval.

## Advanced Configuration

### Custom Resolvers

The package allows you to create and configure your own parameter resolvers. This is useful when you need custom logic for resolving controller action parameters.

To create a custom resolver:

1. Create a class that implements `ParameterResolverInterface`:

```php
use LaminasAttributeController\ParameterResolverInterface;
use LaminasAttributeController\ResolutionContext;

final class MyCustomResolver implements ParameterResolverInterface
{
    public function resolve(ResolutionContext $context): mixed
    {
        // Access parameter information
        $parameter = $context->parameter;

        // Access route match information
        $routeMatch = $context->routeMatch;

        // Access parameter attributes
        $attributes = $context->getAttributes();

        // Implement your custom resolution logic
        // ...

        // Return the resolved value
        return $resolvedValue;
    }
}
```

2. Register your resolver in the configuration:

```php
// In your module.config.php or any other configuration file
return [
    'laminas-attribute-controller' => [
        'resolvers' => [
            // Default resolvers
            FromRouteResolver::class,
            MapRequestPayloadResolver::class,
            MapQueryStringResolver::class,
            QueryParamResolver::class,
            AutowireResolver::class,
            AutoInjectionResolver::class,
            CurrentUserValueResolver::class,
            DefaultValueResolver::class,
            // Your custom resolver
            MyCustomResolver::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            // Factory for your custom resolver
            MyCustomResolver::class => function (ContainerInterface $container) {
                return new MyCustomResolver(/* dependencies */);
            },
        ],
    ],
];
```

The order of resolvers in the configuration is important as they are tried in sequence until one returns a non-null value.

### Default Resolvers

The package comes with the following default resolvers:

- `FromRouteResolver`: Resolves parameters from route matches
- `MapRequestPayloadResolver`: Maps request body to a parameter (POST, PUT, PATCH, etc)
- `MapQueryStringResolver`: Maps request to a parameter from query params (GET request)
- `QueryParamResolver`: Extracts and validates query parameters
- `AutowireResolver`: Provides automatic dependency injection
- `AutoInjectionResolver`: Provides automatic dependency injection
- `CurrentUserValueResolver`: Injects the current authenticated user
- `DefaultValueResolver`: Uses default parameter values

You can customize which resolvers are used by modifying the configuration:

```php
return [
    'laminas-attribute-controller' => [
        'resolvers' => [
            // Only include the resolvers you need
            FromRouteResolver::class,
            MapRequestPayloadResolver::class,
            MapQueryStringResolver::class,
            QueryParamResolver::class,
            AutowireResolver::class,
            AutoInjectionResolver::class,
            CurrentUserValueResolver::class,
            DefaultValueResolver::class,
        ],
    ],
];
```
