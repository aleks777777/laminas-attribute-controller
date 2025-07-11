<?php

declare(strict_types=1);

namespace LaminasAttributeController\Validation;

use Laminas\Http\Exception\InvalidArgumentException;
use Laminas\Http\Request;
use LaminasAttributeController\ParameterResolverInterface;
use LaminasAttributeController\ParameterValue;
use LaminasAttributeController\ResolutionContext;
use ReflectionNamedType;
use Symfony\Component\Validator\Validation;

final readonly class QueryParamResolver implements ParameterResolverInterface
{
    public function __construct(
        private Request $request,
    ) {
    }

    public function resolve(ResolutionContext $context): ParameterValue
    {
        $validator = Validation::createValidator();

        $parameter = $context->parameter;
        /** @var ReflectionNamedType $type */
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType) {
            return ParameterValue::notFound();
        }

        foreach ($context->getAttributes() as $attribute) {
            $instance = $attribute->newInstance();

            if ($instance instanceof QueryParam) {
                $defaultValue = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
                /** @phpstan-ignore-next-line */
                $value = $this->getValueByDotNotation($this->request->getQuery()->toArray(), $instance->name, $defaultValue);

                $value = $value && $type->isBuiltin() ? $this->dynamicCast($type->getName(), $value) : $value;

                if ($instance->required && $value === null) {
                    throw new InvalidArgumentException("Query parameter '{$instance->name}' is required.");
                }

                if (! empty($instance->constraints)) {
                    $violations = $validator->validate($value, $instance->constraints);

                    if (count($violations) > 0) {
                        $violationMessage = $violations->get(0)->getMessage();
                        throw new InvalidArgumentException("Validation failed for '{$instance->name}': $violationMessage");
                    }
                }

                return ParameterValue::found(QueryParam::class, $value);
            }
        }

        return ParameterValue::notFound();
    }

    /**
     * @param  array<string, mixed> $values
     * @param  string               $key
     * @param  mixed|null           $default
     * @return mixed
     */
    public function getValueByDotNotation(array $values, string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);

        foreach ($keys as $key) {
            if (is_array($values) && array_key_exists($key, $values)) {
                $values = $values[$key];
            } else {
                return $default;
            }
        }

        return $values;
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
