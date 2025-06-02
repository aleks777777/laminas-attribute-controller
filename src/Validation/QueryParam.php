<?php

declare(strict_types=1);

namespace LaminasAttributeController\Validation;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute]
final readonly class QueryParam
{
    /**
     * @param Constraint[] $constraints
     */
    public function __construct(
        public string $name,
        public array $constraints = [],
        public bool $required = false,
    ) {
    }
}
