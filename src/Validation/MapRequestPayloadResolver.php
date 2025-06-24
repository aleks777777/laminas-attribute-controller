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

            $value = $this->serializer->deserialize($payload, $context->parameter->getType()->getName(), 'json');
            $result = $this->validator->validate($value);

            if (count($result) > 0) {
                throw new ApiSymfonyValidatorChainException($result);
            }

            return ParameterValue::found(MapRequestPayload::class, $value);
        }

        return ParameterValue::notFound();
    }
}
