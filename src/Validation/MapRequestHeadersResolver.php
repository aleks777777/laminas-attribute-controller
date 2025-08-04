<?php

declare(strict_types=1);

namespace LaminasAttributeController\Validation;

use InvalidArgumentException;
use Laminas\Http\Request;
use LaminasAttributeController\ParameterResolverInterface;
use LaminasAttributeController\ParameterValue;
use LaminasAttributeController\ResolutionContext;
use ReflectionClass;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use function class_exists;
use function count;
use function filter_var;
use function in_array;
use function preg_replace;
use function strtolower;

final readonly class MapRequestHeadersResolver implements ParameterResolverInterface
{
    public function __construct(
        private ValidatorInterface $validator,
        private Request $request,
    ) {
    }

    public function resolve(ResolutionContext $context): ParameterValue
    {
        foreach ($context->getAttributes() as $attribute) {
            if ($attribute->getName() !== MapRequestHeaders::class) {
                continue;
            }

            /** @var MapRequestHeaders $attributeInstance */
            $attributeInstance = $attribute->newInstance();

            if (! class_exists($attributeInstance->dtoClass)) {
                throw new InvalidArgumentException("DTO class {$attributeInstance->dtoClass} does not exist.");
            }

            $reflectionClass = new ReflectionClass($attributeInstance->dtoClass);
            $constructor = $reflectionClass->getConstructor();
            $parameters = $constructor?->getParameters() ?? [];

            $args = [];
            foreach ($parameters as $param) {
                $headerName = $this->toHeaderName($param->getName());

                if (! $this->request->getHeaders()->has($headerName)) {
                    if (in_array($headerName, $attributeInstance->requiredHeaders, true)) {
                        throw new InvalidArgumentException("Missing required header: $headerName");
                    }

                    $args[] = null;
                    continue;
                }

                $headerValue = $this->request->getHeader($headerName)->getFieldValue();

                $typedValue = match ($param->getType()?->getName()) {
                    'int' => (int) $headerValue,
                    'float' => (float) $headerValue,
                    'bool' => filter_var($headerValue, FILTER_VALIDATE_BOOLEAN),
                    default => $headerValue,
                };

                $args[] = $typedValue;
            }

            $dto = $reflectionClass->newInstanceArgs($args);

            $violations = $this->validator->validate($dto);
            if (count($violations) > 0) {
                throw new ApiSymfonyValidatorChainException($violations);
            }

            return ParameterValue::found(MapRequestHeaders::class, $dto);
        }

        return ParameterValue::notFound();
    }

    private function toHeaderName(string $property): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $property));
    }
}
