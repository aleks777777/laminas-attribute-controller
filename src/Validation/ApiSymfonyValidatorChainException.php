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
        public readonly ConstraintViolationListInterface $chain,
        int $code = 400,
    ) {
        parent::__construct('Input validation failed', $code);
    }
}
