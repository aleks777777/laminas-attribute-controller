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
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function count;
use function json_encode;

final readonly class MapQueryStringResolver implements ParameterResolverInterface
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
            if ($attribute->getName() !== MapQueryString::class) {
                continue;
            }

            if (! $context->parameter->getType() instanceof ReflectionNamedType) {
                throw new InvalidArgumentException('For mapping query parameters, type is required');
            }

            /** @phpstan-ignore-next-line */
            $payload = json_encode($this->request->getQuery()->toArray()) ?: '{}';

            Assertion::string($payload, 'Query parameters must be convertible to a string');

            $value = $this->serializer->deserialize($payload, $context->parameter->getType()->getName(), 'json');

            $result = $this->validator->validate($value);

            if (count($result) > 0) {
                throw new ApiSymfonyValidatorChainException($result);
            }

            return ParameterValue::found(MapQueryString::class, $value);
        }

        return ParameterValue::notFound();
    }
}
