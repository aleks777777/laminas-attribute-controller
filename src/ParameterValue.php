<?php

namespace LaminasAttributeController;

final readonly class ParameterValue
{
    public function __construct(
        public bool $found,
        public ?string $attribute,
        public mixed $value,
    ) {
    }

    public static function found(?string $attribute, mixed $value): self
    {
        return new self(true, $attribute, $value);
    }

    public static function notFound(): self
    {
        return new self(false, null, null);
    }
}
