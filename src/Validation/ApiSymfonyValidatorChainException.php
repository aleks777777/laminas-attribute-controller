<?php

declare(strict_types=1);

namespace LaminasAttributeController\Validation;

use RuntimeException;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final class ApiSymfonyValidatorChainException extends RuntimeException
{

    /**
     * @param ConstraintViolationListInterface<ConstraintViolationInterface> $chain
     */
    public function __construct(
        private readonly ConstraintViolationListInterface $chain,
        int $code = 400,
    ) {
        parent::__construct('Input validation failed', $code);
    }

    /**
     * @return array<string, mixed>
     */
    // @INFO: DEV-38510
    public function getErrorDetails(): array
    {
        $error = ['violations' => []];
        /** @var ConstraintViolationInterface $violation */
        foreach ($this->chain as $violation) {
            $propertyPath = str_replace('.', '].children[', $violation->getPropertyPath());
            $propertyPath = 'children[' . $propertyPath . '].data';
            $error['violations'][] = [
                'message' => $violation->getMessage(),
                'invalidValue' => $violation->getInvalidValue(),
                'propertyPath' => $propertyPath,
                'status' => $violation->getCode(),
            ];
        }

        if ($this->getCode()) {
            $error['code'] = $this->getCode();
        }

        return $error;
    }
}