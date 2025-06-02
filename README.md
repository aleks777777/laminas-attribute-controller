# Проект laminas-attribute-controller

## Описание
Этот проект предоставляет контроллеры на основе атрибутов для использования в Laminas. 
Он позволяет упрощенно управлять маршрутами, зависимостями и входными данными в агрументах контроллеров.

## Установка

### Зависимости
- PHP >= 8.0
- Laminas MVC >= 3.0
- Composer

### Инструкция
1. Установите зависимости:
   ```
   composer require aleks777777/laminas-attribute-controller
   ```

## Использование

### Пример контроллера с атрибутами
```php
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\AttributeController\Annotation\Route;

#[Route('/user', name: 'user')]
final class UserController extends AbstractActionController
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

### Регистрация маршрута в конфигурации
Маршруты автоматически регистрируются через аннотации, если модуль подключен.

## Конфигурация
implemented:
```php
- QueryParam
- Route
- AutoInject in methods