<?php

declare(strict_types=1);

namespace LaminasAttributeController\Validation;

use Assert\Assertion;
use JMS\Serializer\SerializerInterface;
use Laminas\Http\Exception\InvalidArgumentException;
use Laminas\Http\Request;
use LaminasAttributeController\ParameterResolverInterface;
use LaminasAttributeController\ParameterValue;
use LaminasAttributeController\ResolutionContext;
use ReflectionNamedType;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use TypeError;

final readonly class MapRequestPayloadResolver implements ParameterResolverInterface
{
    public function __construct(
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private Request $request,
    ) {
    }

    public function resolve(ResolutionContext $context): ParameterValue
    {
        foreach ($context->getAttributes() as $attribute) {
            if ($attribute->getName() !== MapRequestPayload::class) {
                continue;
            }

            if (! $context->parameter->getType() instanceof ReflectionNamedType) {
                throw new InvalidArgumentException('For mapping payload type is required');
            }

            if (! $this->request->getHeader('Content-Type')) {
                return ParameterValue::notFound();
            }

            $contentType = $this->request->getHeader('Content-Type')->getFieldValue();
            $payload = '{}';

            if (str_contains($contentType, 'application/json')) {
                $payload = $this->request->getContent() ?: '{}';
            } elseif (str_contains($contentType, 'application/x-www-form-urlencoded')) {
                $payload = json_encode($this->request->getPost()->toArray());
            }

            Assertion::string($payload, 'Request content must be a string');

            try {
                $value = $this->serializer->deserialize($payload, $context->parameter->getType()->getName(), 'json');
            } catch (TypeError $exception) {
                if (str_contains($exception->getMessage(), 'Cannot assign null to property')) {
                    $this->throwValidationExceptionFromNotNullableField($exception, $context);
                }
                throw $exception;
            }
            $result = $this->validator->validate($value);

            if (count($result) > 0) {
                throw new ApiSymfonyValidatorChainException($result);
            }

            return ParameterValue::found(MapRequestPayload::class, $value);
        }

        return ParameterValue::notFound();
    }

    // Handles object deserialization error when a null value is passed to a non-nullable property; converts the TypeError into an ApiSymfonyValidatorChainException (NotNull) with the correct property path for a consistent API validation error.
    private function throwValidationExceptionFromNotNullableField(TypeError $exception, ResolutionContext $context): never
    {
        $propertyPath = $this->extractPropertyPathFromTypeError($exception) ?: $context->parameter->getName();

        throw new ApiSymfonyValidatorChainException(
            new ConstraintViolationList([
                new ConstraintViolation(
                    "This value should not be null",
                    null,
                    [],
                    null,
                    $propertyPath,
                    null,
                    code: NotNull::IS_NULL_ERROR
                ),
            ]),
        );
    }

    private function extractPropertyPathFromTypeError(TypeError $exception): ?string
    {
        if (preg_match('/::\$(\w+)/', $exception->getMessage(), $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

}
