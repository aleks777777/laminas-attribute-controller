<?php

declare(strict_types=1);

namespace LaminasAttributeController\Validation;

use function implode;
use function rtrim;
use function sprintf;

final class AcceptHeaderItem
{
    public function __construct(
        private string $value,
        private float $quality,
        /** @var array<string, string|null> */
        private array $attributes,
        private int $index,
        private string $original,
    ) {
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getQuality(): float
    {
        return $this->quality;
    }

    /**
     * @return array<string, string|null>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $name): string|null
    {
        return $this->attributes[$name] ?? null;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function __toString(): string
    {
        if ('' !== $this->original) {
            return $this->original;
        }

        $parts = [$this->value];

        if (1.0 !== $this->quality) {
            $parts[] = 'q=' . rtrim(rtrim(sprintf('%.3F', $this->quality), '0'), '.');
        }

        foreach ($this->attributes as $key => $value) {
            $parts[] = null === $value ? $key : $key . '=' . $value;
        }

        return implode(';', $parts);
    }
}
