<?php

declare(strict_types=1);

namespace LaminasAttributeController\Routing;

use Doctrine\ORM\EntityManagerInterface;
use Laminas\Http\Exception\InvalidArgumentException;
use LaminasAttributeController\ParameterResolverInterface;
use LaminasAttributeController\ResolutionContext;
use ReflectionNamedType;
use Throwable;

final readonly class FromRouteResolver implements ParameterResolverInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function resolve(ResolutionContext $context): mixed
    {
        $paramName = $context->parameter->getName();

        // If the parameter is a route parameter, return it
        if (array_key_exists($paramName, $context->routeMatch->getParams())) {
            $type = (string) $context->parameter->getType();
            $value = $context->routeMatch->getParams()[$paramName];

            return $this->dynamicCast($type, $value);
        }

        // If the parameter is a route entity, return the entity
        $paramType = $context->parameter->getType();

        if ($paramType instanceof ReflectionNamedType && ! $paramType->isBuiltin()) {
            $value = $context->routeMatch->getParams()['id'] ?? null;

            if ($value === null) {
                return null;
            }

            /** @var class-string<object> $entityClass */
            $entityClass = $paramType->getName();

            if ($this->isEntity($entityClass)) {
                return $this->loadEntity($entityClass, (int) $value);
            }
        }

        return null;
    }

    /** @param class-string<object> $className */
    private function loadEntity(string $className, int $id): object
    {
        $repository = $this->entityManager->getRepository($className);

        $entity = $repository->find($id);

        if (! $entity) {
            throw new InvalidArgumentException("Entity `$className` not found", 404);
        }

        return $entity;
    }

    private function isEntity(string $className): bool
    {
        try {
            $metaData = $this->entityManager->getClassMetadata($className);

            /* @phpstan-ignore-next-line */
            return $metaData && $metaData->isMappedSuperclass === false;
        } catch (Throwable $e) {
            return false; // if metadata is not found, it's not an entity
        }
    }

    private function dynamicCast(string $type, $value)
    {
        return match (strtolower($type)) {
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value,
            'string' => (string) $value,
            default => throw new InvalidArgumentException("Unsupported type: $type"),
        };
    }
}
